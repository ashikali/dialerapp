<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run():void{User::query()->firstOrCreate(['email'=>env('PBXPRO_ADMIN_EMAIL','admin@example.com')],['tenant_id'=>null,'name'=>'Super Admin','password'=>env('PBXPRO_ADMIN_PASSWORD','ChangeMe-Immediately-2026!'),'role'=>UserRole::SUPER_ADMIN,'status'=>'ACTIVE']);}
}
