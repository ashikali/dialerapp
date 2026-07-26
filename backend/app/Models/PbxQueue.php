<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PbxQueue extends Model
{
    use BelongsToTenant, HasUuids;
    protected $table = 'queues';
    protected $fillable = ['tenant_id','name','number','strategy','wrap_up_seconds','max_wait_seconds','max_size','music_on_hold','status'];
    protected function casts(): array { return ['wrap_up_seconds'=>'integer','max_wait_seconds'=>'integer','max_size'=>'integer']; }
    public function members(): HasMany { return $this->hasMany(QueueMember::class,'queue_id')->orderBy('priority')->orderBy('skill'); }
}
