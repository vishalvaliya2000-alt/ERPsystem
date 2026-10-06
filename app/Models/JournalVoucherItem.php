<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalVoucherItem extends Model
{
    use BelongsToTenant;

    protected $table = 'journal_voucher_items';

    protected $fillable = [
        'journal_voucher_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'remarks',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
