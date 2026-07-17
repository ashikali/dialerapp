<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        $this->socket=@fsockopen($host,$port,$errno,$error,5);if(!$this->socket)throw new \RuntimeException("ESL connection failed: {$error} ({$errno})");stream_set_timeout($this->socket,5);$this->readFrame();$this->send("auth ".config('services.freeswitch.esl_password'));$reply=$this->readFrame();if(!str_contains($reply,'+OK accepted'))throw new \RuntimeException('ESL authentication rejected');$this->send('event plain CHANNEL_CREATE CHANNEL_PROGRESS CHANNEL_PROGRESS_MEDIA CHANNEL_ANSWER CHANNEL_BRIDGE CHANNEL_UNBRIDGE CHANNEL_HOLD CHANNEL_UNHOLD CHANNEL_HANGUP_COMPLETE CUSTOM');Log::info('esl.connected',['host'=>$host,'port'=>$port]);
    }
    private function consume(): void
    {
        do{$item=Redis::blpop(['pbxpro:telephony:commands'],5);if(!$item)continue;$payload=json_decode($item[1],true,512,JSON_THROW_ON_ERROR);$command=$this->mapCommand($payload);$this->send("api {$command}");$reply=$this->readFrame();Log::info('esl.command.executed',['command_id'=>$payload['command_id']??null,'type'=>$payload['type']??null,'reply'=>mb_substr($reply,0,500)]);}while(!$this->option('once'));
    }
    private function mapCommand(array $p): string
    {
        return match($p['type']??''){ 'HANGUP'=>"uuid_kill {$p['leg_uuid']}",'HOLD'=>"uuid_hold {$p['leg_uuid']}",'UNHOLD'=>"uuid_hold off {$p['leg_uuid']}",'BRIDGE'=>"uuid_bridge {$p['agent_leg_uuid']} {$p['customer_leg_uuid']}",'TRANSFER'=>"uuid_transfer {$p['leg_uuid']} {$p['destination']} XML {$p['context']}",'SEND_DTMF'=>"uuid_send_dtmf {$p['leg_uuid']} {$p['digits']}",default=>throw new \InvalidArgumentException('Unsupported telephony command')};
    }
    private function send(string $command): void {if(!is_resource($this->socket))throw new \RuntimeException('ESL is disconnected');fwrite($this->socket,$command."\n\n");}
    private function readFrame(): string {if(!is_resource($this->socket))throw new \RuntimeException('ESL is disconnected');$buffer='';while(!feof($this->socket)){ $line=fgets($this->socket);if($line===false)break;$buffer.=$line;if(str_ends_with($buffer,"\n\n"))break;}return $buffer;}
    private function disconnect(): void {if(is_resource($this->socket))fclose($this->socket);$this->socket=null;}
}
