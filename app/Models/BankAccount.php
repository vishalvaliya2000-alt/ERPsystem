<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'account_title',
        'bank_name',
        'account_number',
        'account_type',
        'ifsc_code',
        'swift_code',
        'branch_name',
        'currency',
        'opening_balance',
        'current_balance',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];
}
