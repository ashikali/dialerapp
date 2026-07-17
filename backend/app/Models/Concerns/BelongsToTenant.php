<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $user = auth()->user();
            if ($user && !$user->isSuperAdmin()) $builder->where($builder->qualifyColumn('tenant_id'), $user->tenant_id);
        });
        static::creating(function ($model): void {
            $user = auth()->user();
            if ($user && !$user->isSuperAdmin()) $model->tenant_id = $user->tenant_id;
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
