<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_extension_with_encrypted_secret(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');

        $response=$this->actingAs($admin)->postJson('/api/v1/extensions', $this->extensionPayload('1001'));

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.extension_number', '1001')
            ->assertJsonMissingPath('data.sip_password');

        $extension=Extension::firstOrFail();
        $this->assertSame('Strong-SIP-Secret-1001', $extension->sip_password);
        $this->assertNotSame('Strong-SIP-Secret-1001', $extension->getRawOriginal('sip_password'));
    }

    public function test_extension_must_be_inside_tenant_range(): void
    {
        [, $admin]=$this->tenantWithAdmin('alpha');

        $this->actingAs($admin)->postJson('/api/v1/extensions', $this->extensionPayload('2001'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extension_number');
    }

    public function test_tenant_admin_can_edit_extension_without_replacing_its_secret(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $extension=Extension::create($this->extensionRecord($tenant,'1001'));
        $secret=$extension->getRawOriginal('sip_password');

        $payload=$this->extensionPayload('1001');
        $payload['sip_password']='';
        $payload['caller_id_name']='John Support';
        $payload['status']='INACTIVE';

        $this->actingAs($admin)->patchJson("/api/v1/extensions/{$extension->id}",$payload)
            ->assertOk()
            ->assertJsonPath('data.caller_id_name','John Support')
            ->assertJsonPath('data.status','INACTIVE')
            ->assertJsonMissingPath('data.sip_password');

        $extension->refresh();
        $this->assertSame($secret,$extension->getRawOriginal('sip_password'));
    }

    public function test_tenant_admin_can_reveal_own_extension_secret_with_audit_entry(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $extension=Extension::create($this->extensionRecord($tenant,'1001'));

        $this->actingAs($admin)
            ->postJson("/api/v1/extensions/{$extension->id}/reveal-password")
            ->assertOk()
            ->assertHeader('Cache-Control','no-store, private')
            ->assertJsonPath('data.sip_password','Strong-SIP-Secret-1001');

        $this->assertDatabaseHas('audit_logs',[
            'tenant_id'=>$tenant->id,
            'user_id'=>$admin->id,
            'action'=>'extension.sip_password_revealed',
            'auditable_id'=>$extension->id,
        ]);
    }

    public function test_tenant_admin_cannot_reveal_another_tenants_extension_secret(): void
    {
        [, $admin]=$this->tenantWithAdmin('alpha');
        [$beta]=$this->tenantWithAdmin('beta');
        $extension=Extension::withoutEvents(fn()=>Extension::forceCreate($this->extensionRecord($beta,'1001')));

        $this->actingAs($admin)
            ->postJson("/api/v1/extensions/{$extension->id}/reveal-password")
            ->assertNotFound()
            ->assertJsonMissingPath('data.sip_password');
    }

    public function test_tenant_admin_can_create_agent_and_assign_own_extension(): void
    {
        [$tenant,$admin]=$this->tenantWithAdmin('alpha');
        $extension=Extension::create($this->extensionRecord($tenant,'1001'));

        $response=$this->actingAs($admin)->postJson('/api/v1/agents', [
            'name'=>'John Smith','display_name'=>'John','employee_code'=>'AGT-1001','username'=>'john','email'=>'john@alpha.test',
            'password'=>'StrongAgentPassword-1001','password_confirmation'=>'StrongAgentPassword-1001','extension_id'=>$extension->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_code', 'AGT-1001')
            ->assertJsonPath('data.user.extensions.0.extension_number', '1001');

        $user=User::where('email','john@alpha.test')->firstOrFail();
        $this->assertSame('john',$user->username);
        $this->assertSame(UserRole::AGENT,$user->role);
        $this->assertSame($tenant->id,$user->tenant_id);
        $this->assertTrue(Hash::check('StrongAgentPassword-1001',$user->password));
        $this->assertDatabaseHas('agents',['user_id'=>$user->id,'tenant_id'=>$tenant->id]);
        $this->assertDatabaseHas('extensions',['id'=>$extension->id,'user_id'=>$user->id]);
    }

    public function test_tenant_admin_cannot_assign_another_tenants_extension(): void
    {
        [, $admin]=$this->tenantWithAdmin('alpha');
        [$beta]=$this->tenantWithAdmin('beta');
        $extension=Extension::withoutEvents(fn()=>Extension::forceCreate($this->extensionRecord($beta,'1001')));

        $this->actingAs($admin)->postJson('/api/v1/agents', [
            'name'=>'John Smith','display_name'=>'John','employee_code'=>'AGT-1001','username'=>'john','email'=>'john@alpha.test',
            'password'=>'StrongAgentPassword-1001','password_confirmation'=>'StrongAgentPassword-1001','extension_id'=>$extension->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('extension_id');

        $this->assertDatabaseMissing('users',['email'=>'john@alpha.test']);
        $this->assertDatabaseCount('agents',0);
    }

    private function tenantWithAdmin(string $code): array
    {
        $tenant=Tenant::create(['name'=>ucfirst($code),'code'=>$code,'sip_domain'=>"{$code}.pbxpro.test",'status'=>'ACTIVE','timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[]]);
        $admin=User::create(['tenant_id'=>$tenant->id,'name'=>'Admin','email'=>"admin@{$code}.test",'password'=>'secret-password','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE']);
        return [$tenant,$admin];
    }

    private function extensionPayload(string $number): array
    {
        return ['extension_number'=>(int)$number,'sip_username'=>$number,'sip_password'=>"Strong-SIP-Secret-{$number}",'caller_id_name'=>'John Smith','caller_id_number'=>$number,'webrtc_enabled'=>true,'voicemail_enabled'=>false,'ring_timeout'=>30];
    }

    private function extensionRecord(Tenant $tenant,string $number): array
    {
        return ['tenant_id'=>$tenant->id,'extension_number'=>$number,'sip_username'=>$number,'sip_password'=>"Strong-SIP-Secret-{$number}",'caller_id_name'=>'John Smith','caller_id_number'=>$number,'status'=>'ACTIVE','webrtc_enabled'=>true,'voicemail_enabled'=>false,'dnd_enabled'=>false,'ring_timeout'=>30];
    }
}
