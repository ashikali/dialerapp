<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use BelongsToTenant, HasUuids;
    protected $fillable = ['tenant_id','agent_id','contact_id','direction','type','status','caller_id','destination_number','started_at','answered_at','bridged_at','ended_at','hangup_cause','disposition_id','notes'];
    protected function casts(): array { return ['started_at'=>'datetime','answered_at'=>'datetime','bridged_at'=>'datetime','ended_at'=>'datetime']; }
}
