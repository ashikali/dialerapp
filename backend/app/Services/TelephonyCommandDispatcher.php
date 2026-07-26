<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TelephonyCommandDispatcher
{
    public function dispatch(string $tenantId,string $type,array $payload): string
    {
        $commandId=(string)Str::uuid();
        $now=now();
        DB::table('telephony_commands')->insert([
            'id'=>$commandId,
            'tenant_id'=>$tenantId,
            'call_id'=>null,
            'type'=>$type,
            'status'=>'PENDING',
            'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),
            'attempts'=>0,
            'error'=>null,
            'processed_at'=>null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        Redis::rpush('pbxpro:telephony:commands',json_encode([
            'command_id'=>$commandId,
            'type'=>$type,
            ...$payload,
        ],JSON_THROW_ON_ERROR));
        return $commandId;
    }
}
