<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'account_code',
        'account_name',
        'account_group',
        'sub_group',
        'opening_balance',
        'current_balance',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function journalVoucherItems(): HasMany
    {
        return $this->hasMany(JournalVoucherItem::class, 'account_id');
    }

    /**
     * Determines whether Debit naturally increases this account group.
     * ASSETS and EXPENSES increase on Debit; LIABILITIES, EQUITY, INCOME increase on Credit.
     */
    public function isDebitNormal(): bool
    {
        return in_array(strtoupper($this->account_group), ['ASSETS', 'EXPENSES'], true);
    }

    /**
     * Recalculates current_balance based on opening balance + journal voucher postings.
     */
    public function recalculateBalance(): float
    {
        $debitTotal = (float) $this->journalVoucherItems()->sum('debit_amount');
        $creditTotal = (float) $this->journalVoucherItems()->sum('credit_amount');
        $opening = (float) $this->opening_balance;

        if ($this->isDebitNormal()) {
            $newBalance = round($opening + $debitTotal - $creditTotal, 2);
        } else {
            $newBalance = round($opening + $creditTotal - $debitTotal, 2);
        }

        $this->update(['current_balance' => $newBalance]);

        return $newBalance;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('account_group', strtoupper($group));
    }
}
