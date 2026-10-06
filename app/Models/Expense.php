<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use BelongsToTenant;

    protected $table = 'expenses';

    protected $fillable = [
        'firm_id',
        'vendor_id',
        'expense_category',
        'vendor_invoice_number',
        'expense_date',
        'base_amount',
        'gst_rate',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
        'is_itc_eligible',
        'itc_type',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'base_amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_itc_eligible' => 'boolean',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function journalVoucher(): HasOne
    {
        return $this->hasOne(JournalVoucher::class, 'reference_id')->where('reference_type', 'EXPENSE');
    }

    public function totalTaxAmount(): float
    {
        return round((float) $this->cgst_amount + (float) $this->sgst_amount + (float) $this->igst_amount, 2);
    }

    public function scopeForFirm(Builder $query, ?int $firmId): Builder
    {
        if (! $firmId) {
            return $query;
        }

        return $query->where('firm_id', $firmId);
    }

    public function scopeItcEligible(Builder $query): Builder
    {
        return $query->where('is_itc_eligible', true);
    }
}
