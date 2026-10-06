<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vendor_code',
        'company_name',
        'contact_person',
        'phone',
        'email',
        'gstin',
        'pan',
        'city',
        'state',
        'country',
        'address',
        'payment_terms_days',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_terms_days' => 'integer',
    ];
}
