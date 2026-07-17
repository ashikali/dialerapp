<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Enums\WorkMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgentSession extends Model
{
    use BelongsToTenant, HasUuids;
    protected $fillable = ['tenant_id','agent_id','extension_id','work_mode','status','current_call_id','login_time','logout_time','device_type','ip_address','user_agent'];
    protected function casts(): array { return ['work_mode'=>WorkMode::class,'status'=>AgentStatus::class,'login_time'=>'datetime','logout_time'=>'datetime']; }
}
