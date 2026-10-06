<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'contact_person',
        'phone',
        'whatsapp',
        'email',
        'city',
        'state',
        'country',
        'source',
        'interested_products',
        'estimated_value',
        'stage',
        'priority',
        'next_action',
        'next_action_date',
        'notes',
        'converted_customer_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'next_action_date' => 'datetime',
    ];

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(FollowupTask::class, 'lead_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'lead_id');
    }
}
