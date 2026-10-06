<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'department_id',
        'role',
        'designation',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'department_id', 'role', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function managedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'manager_user_id');
    }

    public function getFirstNameAttribute(): string
    {
        $parts = explode(' ', trim($this->name ?? 'User'));

        return $parts[0] ?? 'User';
    }

    public function getInitialsAttribute(): string
    {
        $name = trim($this->name ?? 'RD');
        $parts = explode(' ', $name);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    // Role Checks
    public function isOwner(): bool
    {
        return $this->hasRole('owner') || strtolower($this->role ?? '') === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'owner']) || in_array(strtolower($this->role ?? ''), ['admin', 'owner'], true);
    }

    public function isDepartmentManager(): bool
    {
        return $this->hasRole('department_manager') || strtolower($this->role ?? '') === 'department_manager';
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff') || strtolower($this->role ?? '') === 'staff';
    }

    public function isViewer(): bool
    {
        return $this->hasRole('viewer') || strtolower($this->role ?? '') === 'viewer';
    }

    public function isManagerOf(Department $department): bool
    {
        return (int) $this->id === (int) $department->manager_user_id;
    }
}
