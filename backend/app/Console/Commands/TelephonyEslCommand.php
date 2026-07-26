<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class TelephonyEslCommand extends Command
{
    protected $signature='telephony:esl {--once : Consume at most one command}';
    protected $description='Maintain the FreeSWITCH ESL connection and execute durable telephony commands';
    private $socket=null;

    public function handle(): int
    {
        $attempt=0;
        while(true){
            try{$this->connect();$attempt=0;$this->consume();if($this->option('once'))return self::SUCCESS;}
            catch(Throwable $e){Log::error('esl.worker.failure',['exception'=>$e::class,'message'=>$e->getMessage(),'attempt'=>$attempt]);$this->disconnect();if($this->option('once'))return self::FAILURE;sleep(min(30,2**min(++$attempt,5)));}
        }
    }
    private function connect(): void
    {
        $host=config('services.freeswitch.esl_host');$port=config('services.freeswitch.esl_port');
        $this->socket=@fsockopen($host,$port,$errno,$error,5);
        if(!$this->socket)throw new \RuntimeException("ESL connection failed: {$error} ({$errno})");
        stream_set_timeout($this->socket,5);
        $this->readFrame();
        $this->send("auth ".config('services.freeswitch.esl_password'));
        $reply=$this->readFrame();
        if(!str_contains($reply,'+OK accepted'))throw new \RuntimeException('ESL authentication rejected');
        Log::info('esl.connected',['host'=>$host,'port'=>$port]);
    }
    private function consume(): void
    {
        do{
            $item=Redis::blpop(['pbxpro:telephony:commands'],5);
            if(!$item)continue;
            $payload=json_decode($item[1],true,512,JSON_THROW_ON_ERROR);
            $commandId=$payload['command_id']??null;
            if($commandId)DB::table('telephony_commands')->where('id',$commandId)->update(['status'=>'PROCESSING','attempts'=>DB::raw('attempts + 1'),'updated_at'=>now()]);
            try{
                $replies=[];
                foreach($this->mapCommands($payload) as $command){
                    $this->send("api {$command}");
                    $replies[]=mb_substr($this->readFrame(),0,500);
                }
                if($commandId)DB::table('telephony_commands')->where('id',$commandId)->update(['status'=>'COMPLETED','processed_at'=>now(),'error'=>null,'updated_at'=>now()]);
                Log::info('esl.command.executed',['command_id'=>$commandId,'type'=>$payload['type']??null,'replies'=>$replies]);
            }catch(Throwable $exception){
                if($commandId)DB::table('telephony_commands')->where('id',$commandId)->update(['status'=>'FAILED','error'=>mb_substr($exception->getMessage(),0,2000),'processed_at'=>now(),'updated_at'=>now()]);
                throw $exception;
            }
        }while(!$this->option('once'));
    }
    private function mapCommands(array $p): array
    {
        return match($p['type']??''){
            'HANGUP'=>["uuid_kill {$p['leg_uuid']}"],
            'HOLD'=>["uuid_hold {$p['leg_uuid']}"],
            'UNHOLD'=>["uuid_hold off {$p['leg_uuid']}"],
            'BRIDGE'=>["uuid_bridge {$p['agent_leg_uuid']} {$p['customer_leg_uuid']}"],
            'TRANSFER'=>["uuid_transfer {$p['leg_uuid']} {$p['destination']} XML {$p['context']}"],
            'SEND_DTMF'=>["uuid_send_dtmf {$p['leg_uuid']} {$p['digits']}"],
            'CALLCENTER_SYNC_QUEUE'=>$this->callCenterCommands($p),
            default=>throw new \InvalidArgumentException('Unsupported telephony command'),
        };
    }
    private function callCenterCommands(array $payload): array
    {
        $safe='/^[A-Za-z0-9@._:\-\/=\[\]]+$/';
        $queue=(string)($payload['queue_name']??'');
        if(!preg_match($safe,$queue))throw new \InvalidArgumentException('Unsafe queue name');
        if(!($payload['active']??false))return ["callcenter_config queue unload {$queue}"];

        $commands=["callcenter_config queue reload {$queue}","callcenter_config queue load {$queue}"];
        foreach($payload['removed_agents']??[] as $agent){
            if(!preg_match($safe,(string)$agent))throw new \InvalidArgumentException('Unsafe agent name');
            $commands[]="callcenter_config tier del {$queue} {$agent}";
        }
        foreach($payload['members']??[] as $member){
            $agent=(string)($member['agent_name']??'');
            $contact=(string)($member['contact']??'');
            $level=(int)($member['level']??1);
            $position=(int)($member['position']??1);
            if(!preg_match($safe,$agent) || !preg_match($safe,$contact) || $level<1 || $position<1)throw new \InvalidArgumentException('Unsafe call center member');
            $commands[]="callcenter_config agent add {$agent} callback";
            $commands[]="callcenter_config agent set contact {$agent} {$contact}";
            $commands[]="callcenter_config agent set status {$agent} Available";
            $commands[]="callcenter_config tier add {$queue} {$agent} {$level} {$position}";
            $commands[]="callcenter_config tier set level {$queue} {$agent} {$level}";
            $commands[]="callcenter_config tier set position {$queue} {$agent} {$position}";
        }
        return $commands;
    }
    private function send(string $command): void {if(!is_resource($this->socket))throw new \RuntimeException('ESL is disconnected');fwrite($this->socket,$command."\n\n");}
    private function readFrame(): string
    {
        if(!is_resource($this->socket))throw new \RuntimeException('ESL is disconnected');
        $headers='';$contentLength=0;
        while(!feof($this->socket)){
            $line=fgets($this->socket);
            if($line===false)break;
            if($line==="\n" || $line==="\r\n")break;
            $headers.=$line;
            if(preg_match('/^Content-Length:\s*(\d+)/i',$line,$matches))$contentLength=(int)$matches[1];
        }
        $body='';
        while(strlen($body)<$contentLength && !feof($this->socket)){
            $chunk=fread($this->socket,$contentLength-strlen($body));
            if($chunk===false || $chunk==='')break;
            $body.=$chunk;
        }
        return $headers."\n".$body;
    }
    private function disconnect(): void {if(is_resource($this->socket))fclose($this->socket);$this->socket=null;}
}
