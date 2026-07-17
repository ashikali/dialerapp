<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    public function test_tenant_admin_only_sees_own_extensions():void
    {
        [$a,$adminA]=$this->tenantWithAdmin('alpha');[$b]=$this->tenantWithAdmin('beta');
        Extension::withoutEvents(fn()=>Extension::forceCreate($this->extension($a,'1001')));
        Extension::withoutEvents(fn()=>Extension::forceCreate($this->extension($b,'1001')));
        $this->actingAs($adminA)->getJson('/api/v1/extensions')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.tenant_id',$a->id);
    }
    public function test_tenant_id_from_payload_is_ignored():void
    {
        [$a,$adminA]=$this->tenantWithAdmin('alpha');[$b]=$this->tenantWithAdmin('beta');
        $response=$this->actingAs($adminA)->postJson('/api/v1/extensions',$this->extension($b,'1002')+['sip_password'=>'a-very-secure-sip-password']);
        $response->assertCreated();$this->assertDatabaseHas('extensions',['id'=>$response->json('data.id'),'tenant_id'=>$a->id]);
    }
    public function test_super_admin_can_list_all_tenants():void
    {
        $this->tenantWithAdmin('alpha');$this->tenantWithAdmin('beta');$super=User::forceCreate(['name'=>'Root','email'=>'root@example.test','password'=>'secret','role'=>UserRole::SUPER_ADMIN,'status'=>'ACTIVE']);
        $this->actingAs($super)->getJson('/api/v1/tenants')->assertOk()->assertJsonCount(2,'data');
    }
    private function tenantWithAdmin(string $code):array{$tenant=Tenant::create(['name'=>ucfirst($code),'code'=>$code,'sip_domain'=>"{$code}.pbx.test",'status'=>'ACTIVE','timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[]]);$admin=User::forceCreate(['tenant_id'=>$tenant->id,'name'=>'Admin','email'=>"admin@{$code}.test",'password'=>'secret','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE']);return[$tenant,$admin];}
    private function extension(Tenant $tenant,string $number):array{return['tenant_id'=>$tenant->id,'extension_number'=>$number,'sip_username'=>$number,'sip_password'=>'encrypted-at-rest','caller_id_name'=>'Agent','caller_id_number'=>$number,'status'=>'ACTIVE','webrtc_enabled'=>true,'voicemail_enabled'=>false,'dnd_enabled'=>false,'ring_timeout'=>30];}
}
