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
        $data=$request->validate(['domain'=>['required','string'],'user'=>['required','string']]);
        $tenant=Tenant::query()->where('sip_domain',$data['domain'])->where('status','ACTIVE')->firstOrFail();
        $extension=Extension::withoutGlobalScope('tenant')->where('tenant_id',$tenant->id)->where('extension_number',$data['user'])->where('status','ACTIVE')->firstOrFail();
        $xml=new \SimpleXMLElement('<document type="freeswitch/xml"/>'); $section=$xml->addChild('section'); $section->addAttribute('name','directory'); $domain=$section->addChild('domain'); $domain->addAttribute('name',$tenant->sip_domain); $user=$domain->addChild('user'); $user->addAttribute('id',$extension->extension_number); $params=$user->addChild('params'); $param=$params->addChild('param'); $param->addAttribute('name','password'); $param->addAttribute('value',$extension->sip_password); $vars=$user->addChild('variables');
        foreach(['user_context'=>'tenant_'.$tenant->code,'tenant_id'=>$tenant->id,'effective_caller_id_name'=>$extension->caller_id_name,'effective_caller_id_number'=>$extension->caller_id_number] as $name=>$value){$v=$vars->addChild('variable');$v->addAttribute('name',$name);$v->addAttribute('value',(string)$value);}
        return response($xml->asXML(),200,['Content-Type'=>'application/xml']);
    }
    public function emptySection(Request $request, string $section): Response
    {
        $this->authorizeServer($request);
        abort_unless(in_array($section,['dialplan','configuration'],true),404);
        return response("<document type=\"freeswitch/xml\"><section name=\"{$section}\"/></document>",200,['Content-Type'=>'application/xml']);
    }
}
