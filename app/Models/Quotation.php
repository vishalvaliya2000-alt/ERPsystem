<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'firm_id',
        'quotation_number',
        'customer_id',
        'lead_id',
        'recipient_name',
        'recipient_company',
        'recipient_phone',
        'recipient_email',
        'quotation_date',
        'valid_until',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'payment_terms',
        'freight_terms',
        'delivery_timeline',
        'status',
        'notes',
    ];

    protected $casts = [
        'quotation_date' => 'datetime',
        'valid_until' => 'datetime',
        'firm_id' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }
}
