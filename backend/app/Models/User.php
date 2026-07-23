<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;
    protected $fillable = ['tenant_id','name','email','password','role','status'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed','role'=>UserRole::class,'email_verified_at'=>'datetime']; }
    public function isSuperAdmin(): bool { return $this->role === UserRole::SUPER_ADMIN; }
    public function isTenantAdmin(): bool { return $this->role === UserRole::TENANT_ADMIN; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agent(): HasOne { return $this->hasOne(Agent::class); }
    public function extensions(): HasMany { return $this->hasMany(Extension::class); }
}
