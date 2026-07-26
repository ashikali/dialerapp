<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueMember extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable=['tenant_id','queue_id','agent_id','priority','skill'];
    protected function casts(): array { return ['priority'=>'integer','skill'=>'integer']; }
    public function queue(): BelongsTo { return $this->belongsTo(PbxQueue::class,'queue_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
