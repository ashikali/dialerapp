<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_onboard_tenant_and_initial_admin_atomically(): void
    {
        $response=$this->actingAs($this->superAdmin())->postJson('/api/v1/tenants', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'ABC Finance')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.users.0.email', 'admin@abcfinance.test');

        $tenant=Tenant::where('code', 'abc-finance')->firstOrFail();
        $admin=User::where('email', 'admin@abcfinance.test')->firstOrFail();
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertSame(UserRole::TENANT_ADMIN, $admin->role);
        $this->assertTrue(Hash::check('StrongPassword-2026!', $admin->password));
    }

    public function test_tenant_admin_cannot_onboard_another_tenant(): void
    {
        $tenant=$this->tenant();
        $admin=User::create(['tenant_id'=>$tenant->id,'name'=>'Admin','email'=>'existing@example.test','password'=>'secret-password','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE']);

        $this->actingAs($admin)->postJson('/api/v1/tenants', $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('tenants', ['code'=>'abc-finance']);
    }

    public function test_invalid_admin_prevents_tenant_creation(): void
    {
        User::create(['name'=>'Existing','email'=>'admin@abcfinance.test','password'=>'secret-password','role'=>UserRole::SUPER_ADMIN,'status'=>'ACTIVE']);

        $this->actingAs($this->superAdmin())->postJson('/api/v1/tenants', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('admin.email');

        $this->assertDatabaseMissing('tenants', ['code'=>'abc-finance']);
    }

    public function test_suspended_tenant_cannot_use_authenticated_api(): void
    {
        $tenant=$this->tenant(['status'=>'SUSPENDED']);
        $admin=User::create(['tenant_id'=>$tenant->id,'name'=>'Admin','email'=>'suspended@example.test','password'=>'secret-password','role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE']);

        $this->actingAs($admin)->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'This tenant is suspended.');
    }

    private function superAdmin(): User
    {
        return User::create(['name'=>'Root','email'=>fake()->unique()->safeEmail(),'password'=>'secret-password','role'=>UserRole::SUPER_ADMIN,'status'=>'ACTIVE']);
    }

    private function tenant(array $overrides=[]): Tenant
    {
        return Tenant::create(array_merge([
            'name'=>'Existing Tenant','code'=>'existing','sip_domain'=>'existing.pbxpro.test','status'=>'ACTIVE','timezone'=>'UTC','default_language'=>'en',
            'extension_start'=>1000,'extension_end'=>1999,'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ], $overrides));
    }

    private function payload(): array
    {
        return [
            'name'=>'ABC Finance','code'=>'abc-finance','sip_domain'=>'abcfinance.pbxpro.test','timezone'=>'Asia/Kolkata',
            'extension_start'=>1000,'extension_end'=>1999,'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
            'admin'=>[
                'name'=>'ABC Administrator','email'=>'admin@abcfinance.test','password'=>'StrongPassword-2026!',
                'password_confirmation'=>'StrongPassword-2026!',
            ],
        ];
    }
}
