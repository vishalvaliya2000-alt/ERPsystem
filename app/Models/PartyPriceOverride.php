<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions as SpatieLogOptions;

class PartyPriceOverride extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'product_id',
        'custom_rate',
        'custom_hsn',
        'valid_until',
    ];

    protected $casts = [
        'custom_rate' => 'decimal:2',
        'valid_until' => 'date',
    ];

    public function getActivitylogOptions(): SpatieLogOptions
    {
        return SpatieLogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly(['customer_id', 'product_id', 'custom_rate', 'valid_until'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isValid(): bool
    {
        if (! $this->valid_until) {
            return true;
        }

        return $this->valid_until->isFuture() || $this->valid_until->isToday();
    }
}
