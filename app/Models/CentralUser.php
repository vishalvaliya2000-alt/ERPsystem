<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CentralUser extends Authenticatable
{
    use CentralConnection, Notifiable;

    protected $table = 'central_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
