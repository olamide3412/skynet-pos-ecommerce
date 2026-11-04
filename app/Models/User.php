<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is Super Administrator (Bypasses all checks).
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super administrator';
    }

    /**
     * Check if user is Administrator or Super Administrator.
     */
    public function isAdminOrSuperAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->role === 'administrator') {
            return true;
        }

        try {
            return $this->hasRole(['Administrator', 'Super Administrator']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if user can enter backoffice area.
     */
    public function canAccessBackoffice(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Active check: if status is explicitly set to disable, deny access
        if ($this->status === 'disable') {
            return false;
        }

        // Backward compatibility for existing administrator & staff roles
        if (in_array($this->role, ['administrator', 'staff'], true)) {
            return true;
        }

        try {
            return $this->hasPermissionTo('backoffice.access') || $this->hasAnyRole(['Administrator', 'Store Manager', 'Sales / Cashier', 'Warehouse / Fulfillment', 'Rider / Dispatch', 'Catalog Manager', 'Accountant / Finance']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }
}
