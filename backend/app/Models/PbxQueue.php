<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PbxQueue extends Model
{
    use BelongsToTenant, HasUuids;
    protected $table = 'queues';
    protected $fillable = ['tenant_id','name','number','strategy','wrap_up_seconds','max_wait_seconds','max_size','music_on_hold','status'];
}
