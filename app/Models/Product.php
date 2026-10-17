<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_code',
        'product_name',
        'category_id',
        'uom',
        'standard_rate',
        'standard_cost',
        'hsn_code',
        'tax_rate',
        'min_order_qty',
        'packaging',
        'specification_url',
        'description',
        'is_active',
    ];

    protected $casts = [
        'standard_rate' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_order_qty' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function getTaxRatePercentAttribute(): float
    {
        return $this->tax_rate !== null ? (float) $this->tax_rate : 5.00;
    }

    public function setTaxRatePercentAttribute($value): void
    {
        $this->attributes['tax_rate'] = ($value !== null && $value !== '') ? (float) $value : 5.00;
    }

    // Accessor / Mutator Aliases for smooth UI compatibility
    public function getCurrentRatePerKgAttribute(): float
    {
        return (float) ($this->standard_rate ?? 0);
    }

    public function setCurrentRatePerKgAttribute($value): void
    {
        $this->attributes['standard_rate'] = $value;
    }

    public function getDefaultPackagingAttribute(): ?string
    {
        return $this->packaging;
    }

    public function setDefaultPackagingAttribute($value): void
    {
        $this->attributes['packaging'] = $value;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'product_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class, 'product_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class, 'product_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductDocument::class, 'product_id')
            ->where('document_type', ProductDocument::TYPE_PHOTO)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function documentFiles(): HasMany
    {
        return $this->hasMany(ProductDocument::class, 'product_id')
            ->where('document_type', '!=', ProductDocument::TYPE_PHOTO)
            ->orderBy('document_type')
            ->orderByDesc('is_latest')
            ->orderByDesc('created_at');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(ProductDocument::class, 'product_id')
            ->where('document_type', ProductDocument::TYPE_PHOTO)
            ->where('is_primary', true);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ProductDocumentShare::class, 'product_id')->orderByDesc('created_at');
    }

    public function priceSlabs(): HasMany
    {
        return $this->hasMany(ProductPriceSlab::class, 'product_id')->orderBy('min_quantity');
    }

    public function partyOverrides(): HasMany
    {
        return $this->hasMany(PartyPriceOverride::class, 'product_id');
    }

    public function customValues(): HasMany
    {
        return $this->hasMany(ProductCustomValue::class, 'product_id');
    }

    public function getDocumentBadgeSummaryAttribute(): array
    {
        $docs = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $photosCount = $docs->where('document_type', ProductDocument::TYPE_PHOTO)->count();
        $hasCoa = $docs->where('document_type', ProductDocument::TYPE_COA)->isNotEmpty();
        $hasSpec = $docs->where('document_type', ProductDocument::TYPE_SPECIFICATION)->isNotEmpty();
        $hasMsds = $docs->where('document_type', ProductDocument::TYPE_MSDS)->isNotEmpty();
        $otherCount = $docs->where('document_type', ProductDocument::TYPE_OTHER)->count();

        // Check if any COA is expired
        $coaExpired = $docs->where('document_type', ProductDocument::TYPE_COA)
            ->filter(fn ($d) => $d->is_expired)
            ->isNotEmpty();

        return [
            'total_count' => $docs->count(),
            'photos_count' => $photosCount,
            'has_photos' => $photosCount > 0,
            'has_coa' => $hasCoa,
            'coa_expired' => $coaExpired,
            'has_spec' => $hasSpec,
            'has_specification' => $hasSpec,
            'has_msds' => $hasMsds,
            'other_count' => $otherCount,
            'has_other' => $otherCount > 0,
        ];
    }
}
