<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    use BelongsToTenant;

    protected $table = 'asset_depreciations';

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_date',
        'depreciation_amount',
        'book_value_before',
        'book_value_after',
        'journal_voucher_id',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'depreciation_amount' => 'decimal:2',
        'book_value_before' => 'decimal:2',
        'book_value_after' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalVoucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }
}
