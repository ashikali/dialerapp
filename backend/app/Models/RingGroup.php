<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RingGroup extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable=['tenant_id','name','number','strategy','ring_timeout','status'];
    protected function casts(): array { return ['ring_timeout'=>'integer']; }
    public function members(): HasMany { return $this->hasMany(RingGroupMember::class)->orderBy('position'); }
}
