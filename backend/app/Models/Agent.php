<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToTenant, HasUuids;
    protected $fillable = ['tenant_id','user_id','employee_code','display_name','status'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function sessions(): HasMany { return $this->hasMany(AgentSession::class); }
}
