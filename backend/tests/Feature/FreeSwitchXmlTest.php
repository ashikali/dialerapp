<?php

namespace Tests\Feature;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeSwitchXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_lookup_returns_freeswitch_user_xml(): void
    {
        config(['services.freeswitch.xml_token'=>'xml-test-token']);
        $tenant=Tenant::create([
            'name'=>'ABC Finance','code'=>'abcfinance','sip_domain'=>'abcfinance.pbxpro.test','status'=>'ACTIVE',
            'timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,
            'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ]);
        Extension::withoutEvents(fn()=>Extension::forceCreate([
            'tenant_id'=>$tenant->id,'extension_number'=>'1001','sip_username'=>'1001',
            'sip_password'=>'Strong-SIP-Secret-1001','caller_id_name'=>'John Smith','caller_id_number'=>'1001',
            'status'=>'ACTIVE','webrtc_enabled'=>true,'voicemail_enabled'=>false,'dnd_enabled'=>false,'ring_timeout'=>30,
        ]));

        $response=$this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/directory',[
            'domain'=>'abcfinance.pbxpro.test','user'=>'1001',
        ]);

        $response->assertOk()->assertHeader('Content-Type','application/xml');
        $xml=simplexml_load_string($response->getContent());
        $this->assertSame('directory',(string)$xml->section['name']);
        $this->assertSame('abcfinance.pbxpro.test',(string)$xml->section->domain['name']);
        $this->assertSame('dial-string',(string)$xml->section->domain->params->param['name']);
        $this->assertStringContainsString('sofia_contact',(string)$xml->section->domain->params->param['value']);
        $this->assertSame('1001',(string)$xml->section->domain->users->user['id']);
        $variables=[];
        foreach ($xml->section->domain->users->user->variables->variable as $variable) {
            $variables[(string)$variable['name']]=(string)$variable['value'];
        }
        $this->assertSame('pbxpro_internal',$variables['user_context']);
    }

    public function test_missing_directory_entry_returns_freeswitch_not_found_document(): void
    {
        config(['services.freeswitch.xml_token'=>'xml-test-token']);

        $response=$this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/directory',[
            'domain'=>'missing.pbxpro.test','user'=>'9999',
        ]);

        $response->assertOk()
            ->assertSee('<section name="result">',false)
            ->assertSee('<result status="not found"/>',false);
    }

    public function test_registration_lookup_prioritizes_sip_auth_realm_over_profile_ip(): void
    {
        config(['services.freeswitch.xml_token'=>'xml-test-token']);
        $tenant=Tenant::create([
            'name'=>'ABC Finance','code'=>'abcfinance','sip_domain'=>'abcfinance.pbxpro.test','status'=>'ACTIVE',
            'timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,
            'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ]);
        Extension::withoutEvents(fn()=>Extension::forceCreate([
            'tenant_id'=>$tenant->id,'extension_number'=>'1001','sip_username'=>'1001',
            'sip_password'=>'Strong-SIP-Secret-1001','caller_id_name'=>'John Smith','caller_id_number'=>'1001',
            'status'=>'ACTIVE','webrtc_enabled'=>true,'voicemail_enabled'=>false,'dnd_enabled'=>false,'ring_timeout'=>30,
        ]));

        $response=$this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/directory',[
            'action'=>'sip_auth',
            'domain'=>'192.168.1.26',
            'user'=>'1001',
            'sip_auth_realm'=>'abcfinance.pbxpro.test',
            'sip_auth_username'=>'1001',
        ]);

        $response->assertOk()
            ->assertSee('<domain name="abcfinance.pbxpro.test">',false)
            ->assertSee('<user id="1001">',false);
    }
}
