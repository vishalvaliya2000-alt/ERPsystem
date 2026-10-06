<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'product_id',
        'order_qty',
        'rate',
        'order_value',
        'shipped_qty',
        'balance_qty',
        'status',
    ];

    protected $casts = [
        'order_qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'order_value' => 'decimal:2',
        'shipped_qty' => 'decimal:2',
        'balance_qty' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function ($item) {
            if ($item->order_value === null && $item->order_qty !== null && $item->rate !== null) {
                $item->order_value = round((float) $item->order_qty * (float) $item->rate, 2);
            }
            if ($item->balance_qty === null && $item->order_qty !== null) {
                $item->balance_qty = (float) $item->order_qty - (float) ($item->shipped_qty ?? 0);
            }
        });
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(CommercialShipmentItem::class, 'sales_order_item_id');
    }
}
