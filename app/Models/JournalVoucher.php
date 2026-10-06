<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalVoucher extends Model
{
    use BelongsToTenant;

    protected $table = 'journal_vouchers';

    protected $fillable = [
        'firm_id',
        'voucher_number',
        'voucher_date',
        'voucher_type',
        'reference_type',
        'reference_id',
        'narration',
        'total_debit',
        'total_credit',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JournalVoucherItem::class, 'journal_voucher_id');
    }

    /**
     * Checks if the voucher obeys the double-entry balance invariant: Sum(Debit) == Sum(Credit).
     */
    public function isBalanced(): bool
    {
        return abs((float) $this->total_debit - (float) $this->total_credit) < 0.01;
    }

    /**
     * Recalculates debit and credit totals from child items.
     */
    public function recalculateTotals(): void
    {
        $debit = round((float) $this->items()->sum('debit_amount'), 2);
        $credit = round((float) $this->items()->sum('credit_amount'), 2);

        $this->update([
            'total_debit' => $debit,
            'total_credit' => $credit,
        ]);
    }

    public function scopeForFirm(Builder $query, ?int $firmId): Builder
    {
        if (! $firmId) {
            return $query;
        }

        return $query->where('firm_id', $firmId);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('voucher_type', strtoupper($type));
    }
}
