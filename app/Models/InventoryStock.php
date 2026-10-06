<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'current_stock_qty',
        'min_threshold_qty',
        'godown_location',
        'last_audited_at',
    ];

    protected $casts = [
        'current_stock_qty' => 'decimal:2',
        'min_threshold_qty' => 'decimal:2',
        'last_audited_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
