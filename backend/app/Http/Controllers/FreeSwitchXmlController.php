<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeSwitchXmlController extends Controller
{
    private function authorizeServer(Request $request): void
    {
        $provided = $request->header('X-FreeSWITCH-Token') ?: $request->getPassword();
        abort_unless(hash_equals((string)config('services.freeswitch.xml_token'),(string)$provided),401);
    }
    public function directory(Request $request): Response
    {
        $this->authorizeServer($request);
        $domainName=(string)($request->input('domain') ?: $request->input('sip_auth_realm') ?: '');
        $userName=(string)($request->input('user') ?: $request->input('sip_auth_username') ?: '');
        if ($domainName==='' && $request->input('tag_name')==='domain') $domainName=(string)$request->input('key_value','');
        if ($userName==='' && $request->input('key_name')==='id') $userName=(string)$request->input('key_value','');
        if ($domainName==='' || $userName==='') return $this->notFound();

        $tenant=Tenant::query()->where('sip_domain',$domainName)->where('status','ACTIVE')->first();
        if (!$tenant) return $this->notFound();
        $extension=Extension::withoutGlobalScope('tenant')->where('tenant_id',$tenant->id)->where('extension_number',$userName)->where('status','ACTIVE')->first();
        if (!$extension) return $this->notFound();

        $xml=new \SimpleXMLElement('<document type="freeswitch/xml"/>'); $section=$xml->addChild('section'); $section->addAttribute('name','directory'); $domain=$section->addChild('domain'); $domain->addAttribute('name',$tenant->sip_domain); $users=$domain->addChild('users'); $user=$users->addChild('user'); $user->addAttribute('id',$extension->extension_number); $params=$user->addChild('params'); $param=$params->addChild('param'); $param->addAttribute('name','password'); $param->addAttribute('value',$extension->sip_password); $vars=$user->addChild('variables');
        foreach(['user_context'=>'pbxpro_internal','tenant_id'=>$tenant->id,'effective_caller_id_name'=>$extension->caller_id_name,'effective_caller_id_number'=>$extension->caller_id_number] as $name=>$value){$v=$vars->addChild('variable');$v->addAttribute('name',$name);$v->addAttribute('value',(string)$value);}
        return response($xml->asXML(),200,['Content-Type'=>'application/xml']);
    }
    public function emptySection(Request $request, string $section): Response
    {
        $this->authorizeServer($request);
        abort_unless(in_array($section,['dialplan','configuration'],true),404);
        return response("<document type=\"freeswitch/xml\"><section name=\"{$section}\"/></document>",200,['Content-Type'=>'application/xml']);
    }

    private function notFound(): Response
    {
        return response('<?xml version="1.0"?><document type="freeswitch/xml"><section name="result"><result status="not found"/></section></document>',200,['Content-Type'=>'application/xml']);
    }
}
