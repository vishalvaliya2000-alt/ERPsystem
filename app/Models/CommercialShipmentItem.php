<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialShipmentItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'commercial_shipment_id',
        'sales_order_item_id',
        'batch_number',
        'quantity',
        'unit_value',
        'total_value',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'commercial_shipment_item_id');
    }

    public function getInvoicedQuantityAttribute(): float
    {
        return (float) $this->invoiceItems()->sum('quantity');
    }

    public function getInvoicedAmountAttribute(): float
    {
        return (float) $this->invoiceItems()->sum('amount');
    }

    public function getRemainingInvoicableQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - $this->invoiced_quantity);
    }

    public function getRemainingInvoicableAmountAttribute(): float
    {
        return max(0, (float) $this->total_value - $this->invoiced_amount);
    }

    public function isFullyInvoiced(): bool
    {
        return $this->remaining_invoicable_amount <= 0 && $this->total_value > 0;
    }
}
