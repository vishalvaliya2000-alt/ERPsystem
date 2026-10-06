<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'commercial_shipment_item_id',
        'commercial_shipment_id',
        'sales_order_item_id',
        'sales_order_id',
        'product_id',
        'batch_number',
        'description',
        'hsn_code',
        'quantity',
        'uom',
        'rate',
        'unit_cost',
        'line_cost',
        'line_profit',
        'amount',
        'gst_rate',
        'gst_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_cost' => 'decimal:2',
        'line_profit' => 'decimal:2',
        'amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'gst_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function commercialShipmentItem(): BelongsTo
    {
        return $this->belongsTo(CommercialShipmentItem::class, 'commercial_shipment_item_id');
    }

    public function commercialShipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }
}
