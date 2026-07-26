<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingGroupMember extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable=['tenant_id','ring_group_id','extension_id','position'];
    protected function casts(): array { return ['position'=>'integer']; }
    public function ringGroup(): BelongsTo { return $this->belongsTo(RingGroup::class); }
    public function extension(): BelongsTo { return $this->belongsTo(Extension::class); }
}
