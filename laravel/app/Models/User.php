<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    // ── Helpers de role ───────────────────────────────────────────────────────

    public function isAdminGlobal(): bool
    {
        return $this->role === 'admin_global';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin_global', 'admin']);
    }

    public function isOperador(): bool
    {
        return $this->role === 'operador';
    }

    /**
     * Escopo de tenant para queries: admin_global não filtra,
     * os demais filtram pelo próprio tenant.
     */
    public function tenantScope(): ?int
    {
        return $this->isAdminGlobal() ? null : $this->tenant_id;
    }
}