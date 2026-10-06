<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceSlab extends Model
{
    protected $fillable = [
        'product_id',
        'tier_name',
        'min_quantity',
        'rate',
    ];

    protected $casts = [
        'min_quantity' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
