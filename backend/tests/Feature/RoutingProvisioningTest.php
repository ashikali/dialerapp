<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RoutingProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_ring_group_and_dynamic_dialplan_bridge(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $extension=$this->extension($tenant,'1001');

        $response=$this->actingAs($admin)->postJson('/api/v1/ring-groups',[
            'name'=>'Reception','number'=>'7000','strategy'=>'SIMULTANEOUS','ring_timeout'=>25,
            'status'=>'ACTIVE','member_extension_ids'=>[$extension->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.number','7000')
            ->assertJsonPath('data.members.0.extension.extension_number','1001');

        config(['services.freeswitch.xml_token'=>'xml-test-token']);
        $this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/dialplan',[
            'variable_tenant_id'=>$tenant->id,
            'Caller-Destination-Number'=>'7000',
        ])->assertOk()
            ->assertSee('application="bridge"',false)
            ->assertSee('user/1001@alpha.pbxpro.test',false);
    }

    public function test_tenant_admin_can_create_queue_and_render_callcenter_configuration(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $agent=$this->agentWithExtension($tenant,'1001');
        Redis::shouldReceive('rpush')->once()->andReturn(1);

        $response=$this->actingAs($admin)->postJson('/api/v1/queues',[
            'name'=>'Support','number'=>'6000','strategy'=>'longest-idle-agent',
            'wrap_up_seconds'=>30,'max_wait_seconds'=>300,'max_size'=>100,
            'music_on_hold'=>'local_stream://moh','status'=>'ACTIVE','member_agent_ids'=>[$agent->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.number','6000')
            ->assertJsonPath('data.members.0.agent.id',$agent->id);
        $this->assertDatabaseHas('telephony_commands',['tenant_id'=>$tenant->id,'type'=>'CALLCENTER_SYNC_QUEUE','status'=>'PENDING']);

        config(['services.freeswitch.xml_token'=>'xml-test-token']);
        $this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/configuration',[
            'key_value'=>'callcenter.conf',
        ])->assertOk()
            ->assertSee('queue name="6000@alpha.pbxpro.test"',false)
            ->assertSee('agent name="'.$agent->id.'@alpha.pbxpro.test"',false)
            ->assertSee('tier agent="'.$agent->id.'@alpha.pbxpro.test"',false);

        $this->withBasicAuth('pbxpro','xml-test-token')->post('/freeswitch/xml/dialplan',[
            'variable_domain_name'=>'alpha.pbxpro.test',
            'Caller-Destination-Number'=>'6000',
        ])->assertOk()
            ->assertSee('application="callcenter"',false)
            ->assertSee('data="6000@alpha.pbxpro.test"',false);
    }

    public function test_destination_numbers_cannot_collide_across_resource_types(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $extension=$this->extension($tenant,'1001');

        $this->actingAs($admin)->postJson('/api/v1/ring-groups',[
            'name'=>'Collision','number'=>'1001','strategy'=>'SIMULTANEOUS','ring_timeout'=>30,
            'status'=>'ACTIVE','member_extension_ids'=>[$extension->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('number');
    }

    public function test_ring_group_cannot_use_another_tenants_extension(): void
    {
        [, $admin]=$this->tenantWithAdmin('alpha');
        [$beta]=$this->tenantWithAdmin('beta');
        $extension=$this->extension($beta,'1001');

        $this->actingAs($admin)->postJson('/api/v1/ring-groups',[
            'name'=>'Invalid','number'=>'7000','strategy'=>'SIMULTANEOUS','ring_timeout'=>30,
            'status'=>'ACTIVE','member_extension_ids'=>[$extension->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('member_extension_ids.0');
    }

    private function tenantWithAdmin(string $code): array
    {
        $tenant=Tenant::create([
            'name'=>ucfirst($code),'code'=>$code,'sip_domain'=>"{$code}.pbxpro.test",'status'=>'ACTIVE',
            'timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>7999,
            'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ]);
        $admin=User::create([
            'tenant_id'=>$tenant->id,'name'=>'Admin','email'=>"admin@{$code}.test",
            'password'=>'secret-password','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE',
        ]);
        return [$tenant,$admin];
    }

    private function extension(Tenant $tenant,string $number,?User $user=null): Extension
    {
        return Extension::withoutEvents(fn()=>Extension::forceCreate([
            'tenant_id'=>$tenant->id,'user_id'=>$user?->id,'extension_number'=>$number,'sip_username'=>$number,
            'sip_password'=>"Strong-SIP-Secret-{$number}",'caller_id_name'=>'Agent '.$number,'caller_id_number'=>$number,
            'status'=>'ACTIVE','webrtc_enabled'=>true,'voicemail_enabled'=>false,'dnd_enabled'=>false,'ring_timeout'=>30,
        ]));
    }

    private function agentWithExtension(Tenant $tenant,string $number): Agent
    {
        $user=User::create([
            'tenant_id'=>$tenant->id,'name'=>'Agent '.$number,'email'=>"agent{$number}@{$tenant->code}.test",
            'password'=>'secret-password','role'=>UserRole::AGENT,'status'=>'ACTIVE',
        ]);
        $agent=Agent::create([
            'tenant_id'=>$tenant->id,'user_id'=>$user->id,'employee_code'=>'AGT-'.$number,
            'display_name'=>'Agent '.$number,'status'=>'ACTIVE',
        ]);
        $this->extension($tenant,$number,$user);
        return $agent;
    }
}
