<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCustomField extends Model
{
    protected $fillable = [
        'field_name',
        'field_key',
        'field_type',
        'options',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ProductCustomValue::class, 'custom_field_id');
    }
}
