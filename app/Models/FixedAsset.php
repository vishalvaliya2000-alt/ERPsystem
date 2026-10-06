<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use BelongsToTenant;

    protected $table = 'fixed_assets';

    protected $fillable = [
        'firm_id',
        'asset_code',
        'asset_name',
        'asset_category',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'depreciation_rate',
        'accumulated_depreciation',
        'current_book_value',
        'status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'useful_life_years' => 'integer',
        'depreciation_rate' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'current_book_value' => 'decimal:2',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class, 'fixed_asset_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForFirm(Builder $query, ?int $firmId): Builder
    {
        if (! $firmId) {
            return $query;
        }

        return $query->where('firm_id', $firmId);
    }
}
