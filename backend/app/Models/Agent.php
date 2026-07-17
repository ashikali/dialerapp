<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use BelongsToTenant, HasUuids;
    protected $fillable = ['tenant_id','user_id','employee_code','display_name','status'];
    public function user() { return $this->belongsTo(User::class); }
    public function sessions() { return $this->hasMany(AgentSession::class); }
}
