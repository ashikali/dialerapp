<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;
    protected $fillable = ['name','code','sip_domain','status','timezone','default_language','extension_start','extension_end','max_extensions','max_agents','max_queues','max_campaigns','max_concurrent_calls','recording_retention_days','features'];
    protected function casts(): array { return ['features'=>'array','max_extensions'=>'integer','max_agents'=>'integer','max_queues'=>'integer','max_campaigns'=>'integer','max_concurrent_calls'=>'integer','recording_retention_days'=>'integer']; }
}
