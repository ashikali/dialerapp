<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users',function(Blueprint $table): void {
            $table->string('username',64)->nullable();
        });

        $used=[];
        DB::table('users')->whereNotNull('tenant_id')->orderBy('created_at')->cursor()->each(
            function(object $user) use(&$used): void {
                $local=strtolower(strstr((string)$user->email,'@',true) ?: (string)$user->email);
                $base=trim((string)preg_replace('/[^a-z0-9._-]+/','',$local),'.-_');
                $base=substr($base!==''?$base:'user',0,64);
                $candidate=$base;
                $suffix=2;
                while(isset($used[$user->tenant_id][$candidate])){
                    $tail='-'.$suffix++;
                    $candidate=substr($base,0,64-strlen($tail)).$tail;
                }
                $used[$user->tenant_id][$candidate]=true;
                DB::table('users')->where('id',$user->id)->update(['username'=>$candidate]);
            }
        );

        Schema::table('users',function(Blueprint $table): void {
            $table->unique(['tenant_id','username']);
        });
    }

    public function down(): void
    {
        Schema::table('users',function(Blueprint $table): void {
            $table->dropUnique(['tenant_id','username']);
            $table->dropColumn('username');
        });
    }
};
