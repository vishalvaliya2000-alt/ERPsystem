<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCustomValue extends Model
{
    protected $fillable = [
        'product_id',
        'custom_field_id',
        'field_value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(ProductCustomField::class, 'custom_field_id');
    }
}
