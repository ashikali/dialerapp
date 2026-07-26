<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\PbxQueue;
use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeSwitchXmlController extends Controller
{
    public function directory(Request $request): Response
    {
        $this->authorizeServer($request);
        $domainName=(string)($request->input('sip_auth_realm') ?: $request->input('domain') ?: '');
        $userName=(string)($request->input('sip_auth_username') ?: $request->input('user') ?: '');
        if($domainName==='' && $request->input('tag_name')==='domain')$domainName=(string)$request->input('key_value','');
        if($userName==='' && $request->input('key_name')==='id')$userName=(string)$request->input('key_value','');
        if($domainName==='' || $userName==='')return $this->notFound();

        $tenant=Tenant::query()->where('sip_domain',$domainName)->where('status','ACTIVE')->first();
        if(!$tenant)return $this->notFound();
        $extension=Extension::withoutGlobalScope('tenant')->where('tenant_id',$tenant->id)->where('extension_number',$userName)->where('status','ACTIVE')->first();
        if(!$extension)return $this->notFound();

        $xml=$this->document('directory');
        $domain=$xml->section->addChild('domain');
        $domain->addAttribute('name',$tenant->sip_domain);
        $domainParams=$domain->addChild('params');
        $this->param($domainParams,'dial-string','{^^:sip_invite_domain=${dialed_domain}:presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(*/${dialed_user}@${dialed_domain})}');
        $users=$domain->addChild('users');
        $user=$users->addChild('user');
        $user->addAttribute('id',$extension->extension_number);
        $params=$user->addChild('params');
        $this->param($params,'password',$extension->sip_password);
        $variables=$user->addChild('variables');
        foreach([
            'user_context'=>'pbxpro_internal',
            'tenant_id'=>$tenant->id,
            'domain_name'=>$tenant->sip_domain,
            'effective_caller_id_name'=>$extension->caller_id_name,
            'effective_caller_id_number'=>$extension->caller_id_number,
        ] as $name=>$value)$this->variable($variables,$name,(string)$value);
        return $this->xmlResponse($xml);
    }

    public function dialplan(Request $request): Response
    {
        $this->authorizeServer($request);
        $destination=$this->firstInput($request,['Caller-Destination-Number','caller_destination_number','variable_destination_number','destination_number']);
        if(!preg_match('/^\d{2,16}$/',$destination))return $this->notFound();
        $tenant=$this->resolveTenant($request);
        if(!$tenant)return $this->notFound();

        $xml=$this->document('dialplan');
        $context=$xml->section->addChild('context');
        $context->addAttribute('name','pbxpro_internal');
        $extensionXml=$context->addChild('extension');
        $extensionXml->addAttribute('name','pbxpro_destination_'.$destination);
        $condition=$extensionXml->addChild('condition');
        $condition->addAttribute('field','destination_number');
        $condition->addAttribute('expression','^'.preg_quote($destination,'/').'$');

        $extension=Extension::withoutGlobalScope('tenant')->where('tenant_id',$tenant->id)->where('extension_number',$destination)->where('status','ACTIVE')->first();
        if($extension){
            $this->action($condition,'set','hangup_after_bridge=true');
            $this->action($condition,'bridge','user/'.$destination.'@'.$tenant->sip_domain);
            return $this->xmlResponse($xml);
        }

        $ringGroup=RingGroup::withoutGlobalScope('tenant')
            ->with(['members.extension'=>fn($query)=>$query->where('status','ACTIVE')])
            ->where('tenant_id',$tenant->id)->where('number',$destination)->where('status','ACTIVE')->first();
        if($ringGroup){
            $targets=$ringGroup->members->filter(fn($member)=>$member->extension)->map(
                fn($member)=>'[leg_timeout='.$ringGroup->ring_timeout.']user/'.$member->extension->extension_number.'@'.$tenant->sip_domain
            )->values();
            if($targets->isNotEmpty()){
                $this->action($condition,'set','hangup_after_bridge=true');
                $this->action($condition,'bridge',$targets->implode($ringGroup->strategy==='SIMULTANEOUS'?',':'|'));
                return $this->xmlResponse($xml);
            }
        }

        $queue=PbxQueue::withoutGlobalScope('tenant')->where('tenant_id',$tenant->id)->where('number',$destination)->where('status','ACTIVE')->first();
        if($queue){
            $this->action($condition,'answer');
            $this->action($condition,'set','hangup_after_bridge=true');
            $this->action($condition,'callcenter',$queue->number.'@'.$tenant->sip_domain);
            return $this->xmlResponse($xml);
        }

        $this->action($condition,'hangup','UNALLOCATED_NUMBER');
        return $this->xmlResponse($xml);
    }

    public function configuration(Request $request): Response
    {
        $this->authorizeServer($request);
        $configurationName=$this->firstInput($request,['key_value','configuration','name']);
        if(!str_contains($configurationName,'callcenter.conf'))return $this->notFound();

        $queues=PbxQueue::withoutGlobalScope('tenant')
            ->with([
                'tenant:id,sip_domain,status',
                'members.agent.user.extensions'=>fn($query)=>$query->where('status','ACTIVE'),
            ])
            ->where('status','ACTIVE')
            ->whereHas('tenant',fn($query)=>$query->where('status','ACTIVE'))
            ->get();

        $xml=$this->document('configuration');
        $configuration=$xml->section->addChild('configuration');
        $configuration->addAttribute('name','callcenter.conf');
        $configuration->addAttribute('description','PBXPro dynamic multi-tenant call center');
        $settings=$configuration->addChild('settings');
        $this->param($settings,'reserve-agents','true');
        $queueElements=$configuration->addChild('queues');
        $agentElements=$configuration->addChild('agents');
        $tierElements=$configuration->addChild('tiers');
        $renderedAgents=[];

        foreach($queues as $queue){
            $queueName=$queue->number.'@'.$queue->tenant->sip_domain;
            $queueElement=$queueElements->addChild('queue');
            $queueElement->addAttribute('name',$queueName);
            foreach([
                'strategy'=>$queue->strategy,
                'moh-sound'=>$queue->music_on_hold,
                'time-base-score'=>'queue',
                'max-wait-time'=>$queue->max_wait_seconds,
                'max-wait-time-with-no-agent'=>$queue->max_wait_seconds,
                'max-wait-time-with-no-agent-time-reached'=>5,
                'tier-rules-apply'=>'false',
                'discard-abandoned-after'=>60,
                'abandoned-resume-allowed'=>'false',
                'skip-agents-with-external-calls'=>'true',
            ] as $name=>$value)$this->param($queueElement,$name,(string)$value);

            foreach($queue->members as $member){
                $extension=$member->agent?->user?->extensions?->first();
                if(!$extension || $member->agent->status!=='ACTIVE')continue;
                $agentName=$member->agent_id.'@'.$queue->tenant->sip_domain;
                if(!isset($renderedAgents[$agentName])){
                    $agent=$agentElements->addChild('agent');
                    $agent->addAttribute('name',$agentName);
                    $agent->addAttribute('type','callback');
                    $agent->addAttribute('contact','[leg_timeout='.$extension->ring_timeout.']user/'.$extension->extension_number.'@'.$queue->tenant->sip_domain);
                    $agent->addAttribute('status','Available');
                    $agent->addAttribute('max-no-answer','3');
                    $agent->addAttribute('wrap-up-time',(string)$queue->wrap_up_seconds);
                    $agent->addAttribute('reject-delay-time','10');
                    $agent->addAttribute('busy-delay-time','30');
                    $agent->addAttribute('no-answer-delay-time','10');
                    $renderedAgents[$agentName]=true;
                }
                $tier=$tierElements->addChild('tier');
                $tier->addAttribute('agent',$agentName);
                $tier->addAttribute('queue',$queueName);
                $tier->addAttribute('level',(string)$member->priority);
                $tier->addAttribute('position',(string)$member->skill);
            }
        }
        return $this->xmlResponse($xml);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantId=$this->firstInput($request,['variable_tenant_id','tenant_id']);
        if($tenantId!==''){
            $tenant=Tenant::query()->whereKey($tenantId)->where('status','ACTIVE')->first();
            if($tenant)return $tenant;
        }
        $domain=$this->firstInput($request,['variable_domain_name','domain_name','sip_auth_realm','variable_sip_from_host','sip_from_host','domain']);
        return $domain===''?null:Tenant::query()->where('sip_domain',$domain)->where('status','ACTIVE')->first();
    }

    private function authorizeServer(Request $request): void
    {
        $provided=$request->header('X-FreeSWITCH-Token') ?: $request->getPassword();
        abort_unless(hash_equals((string)config('services.freeswitch.xml_token'),(string)$provided),401);
    }

    private function document(string $sectionName): \SimpleXMLElement
    {
        $xml=new \SimpleXMLElement('<document type="freeswitch/xml"/>');
        $section=$xml->addChild('section');
        $section->addAttribute('name',$sectionName);
        return $xml;
    }

    private function firstInput(Request $request,array $names): string
    {
        foreach($names as $name){
            $value=(string)$request->input($name,'');
            if($value!=='')return $value;
        }
        return '';
    }

    private function param(\SimpleXMLElement $parent,string $name,string $value): void
    {
        $param=$parent->addChild('param');
        $param->addAttribute('name',$name);
        $param->addAttribute('value',$value);
    }

    private function variable(\SimpleXMLElement $parent,string $name,string $value): void
    {
        $variable=$parent->addChild('variable');
        $variable->addAttribute('name',$name);
        $variable->addAttribute('value',$value);
    }

    private function action(\SimpleXMLElement $condition,string $application,?string $data=null): void
    {
        $action=$condition->addChild('action');
        $action->addAttribute('application',$application);
        if($data!==null)$action->addAttribute('data',$data);
    }

    private function xmlResponse(\SimpleXMLElement $xml): Response
    {
        return response($xml->asXML(),200,['Content-Type'=>'application/xml','Cache-Control'=>'no-store']);
    }

    private function notFound(): Response
    {
        return response('<?xml version="1.0"?><document type="freeswitch/xml"><section name="result"><result status="not found"/></section></document>',200,['Content-Type'=>'application/xml','Cache-Control'=>'no-store']);
    }
}
