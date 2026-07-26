<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_login_with_scoped_username(): void
    {
        [$tenant,$admin]=$this->tenantAdmin('abcfinance','admin','admin@company.com');

        $this->withHeader('Host',$tenant->sip_domain)->postJson('/api/v1/auth/login',[
            'login'=>'admin',
            'password'=>'Master2022!PBX',
        ])->assertOk()
            ->assertJsonPath('user.id',$admin->id)
            ->assertJsonPath('user.username','admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_tenant_user_can_login_with_domain_qualified_username_or_contact_email(): void
    {
        [$tenant,$admin]=$this->tenantAdmin('abcfinance','admin','owner@company.com');

        $this->withHeader('Host',$tenant->sip_domain)->postJson('/api/v1/auth/login',[
            'login'=>'admin@abcfinance.pbxpro.test',
            'password'=>'Master2022!PBX',
        ])->assertOk()->assertJsonPath('user.id',$admin->id);

        auth()->logout();

        $this->withHeader('Host',$tenant->sip_domain)->postJson('/api/v1/auth/login',[
            'login'=>'owner@company.com',
            'password'=>'Master2022!PBX',
        ])->assertOk()->assertJsonPath('user.id',$admin->id);
    }

    public function test_username_is_scoped_to_the_requested_tenant_domain(): void
    {
        [$alpha]=$this->tenantAdmin('alpha','admin','alpha@company.com');
        [, $betaAdmin]=$this->tenantAdmin('beta','admin','beta@company.com');

        $this->withHeader('Host',$alpha->sip_domain)->postJson('/api/v1/auth/login',[
            'login'=>'admin',
            'password'=>'Master2022!PBX',
        ])->assertOk()
            ->assertJsonPath('user.id',User::where('tenant_id',$alpha->id)->firstOrFail()->id)
            ->assertJsonMissing(['id'=>$betaAdmin->id]);
    }

    public function test_super_admin_uses_contact_email_on_platform_domain(): void
    {
        $super=User::create([
            'name'=>'Super Admin','email'=>'root@example.com','password'=>'Master2022!PBX',
            'role'=>UserRole::SUPER_ADMIN,'status'=>'ACTIVE',
        ]);

        $this->withHeader('Host','pbxpro.test')->postJson('/api/v1/auth/login',[
            'login'=>'root@example.com',
            'password'=>'Master2022!PBX',
        ])->assertOk()->assertJsonPath('user.id',$super->id);
    }

    private function tenantAdmin(string $code,string $username,string $email): array
    {
        $tenant=Tenant::create([
            'name'=>ucfirst($code),'code'=>$code,'sip_domain'=>"{$code}.pbxpro.test",'status'=>'ACTIVE',
            'timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,
            'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ]);
        $admin=User::create([
            'tenant_id'=>$tenant->id,'name'=>'Administrator','username'=>$username,'email'=>$email,
            'password'=>'Master2022!PBX','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE',
        ]);
        return [$tenant,$admin];
    }
}
