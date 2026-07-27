<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyLoginCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_login_form_can_still_send_email_field(): void
    {
        $tenant=Tenant::create([
            'name'=>'ABC Finance','code'=>'abcfinance','sip_domain'=>'abcfinance.pbxpro.test','status'=>'ACTIVE',
            'timezone'=>'UTC','default_language'=>'en','extension_start'=>1000,'extension_end'=>1999,
            'max_extensions'=>100,'max_agents'=>50,'max_queues'=>10,'max_campaigns'=>0,
            'max_concurrent_calls'=>25,'recording_retention_days'=>90,'features'=>[],
        ]);
        $admin=User::create([
            'tenant_id'=>$tenant->id,'name'=>'Administrator','username'=>'admin',
            'email'=>'admin@abcfinance.test','password'=>'Master2022!PBX',
            'role'=>UserRole::TENANT_ADMIN,'status'=>'ACTIVE',
        ]);

        $this->withHeader('Host',$tenant->sip_domain)->postJson('/api/v1/auth/login',[
            'email'=>'admin@abcfinance.pbxpro.test',
            'password'=>'Master2022!PBX',
        ])->assertOk()->assertJsonPath('user.id',$admin->id);
    }
}
