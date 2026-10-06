<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'firm_id',
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'commercial_shipment_id',
        'linked_po_numbers',
        'invoice_date',
        'due_date',
        'material_subtotal',
        'freight_amount',
        'taxable_amount',
        'subtotal',
        'gst_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'tcs_rate',
        'tcs_amount',
        'tds_rate',
        'tds_amount',
        'total_amount',
        'amount_received',
        'balance_due',
        'total_cost_amount',
        'gross_profit_amount',
        'gross_profit_percentage',
        'status',
        'ads_enabled',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
        'firm_id' => 'integer',
        'material_subtotal' => 'decimal:2',
        'freight_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'tcs_rate' => 'decimal:3',
        'tcs_amount' => 'decimal:2',
        'tds_rate' => 'decimal:3',
        'tds_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_cost_amount' => 'decimal:2',
        'gross_profit_amount' => 'decimal:2',
        'gross_profit_percentage' => 'decimal:2',
        'ads_enabled' => 'boolean',
    ];

    protected $appends = [
        'invoice_date_formatted',
        'due_date_formatted',
    ];

    public function getInvoiceDateFormattedAttribute(): string
    {
        return $this->invoice_date ? $this->invoice_date->format('Y-m-d') : '';
    }

    public function getDueDateFormattedAttribute(): string
    {
        return $this->due_date ? $this->due_date->format('Y-m-d') : '';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function commercialShipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }

    public function getEffectiveShipmentAttribute(): ?CommercialShipment
    {
        if ($this->commercial_shipment_id) {
            return $this->commercialShipment;
        }

        if ($this->sales_order_id) {
            return CommercialShipment::whereHas('items.salesOrderItem', function ($q) {
                $q->where('sales_order_id', $this->sales_order_id);
            })->latest('shipment_date')->first();
        }

        return null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'invoice_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class, 'invoice_id');
    }

    public function invoiceDocument(): HasOne
    {
        return $this->hasOne(BusinessDocument::class, 'invoice_id')
            ->ofMany(['id' => 'max'], function ($q) {
                $q->where('document_type', 'INVOICE');
            });
    }

    public function getDisplayPoNumbersAttribute(): string
    {
        if (! empty($this->linked_po_numbers)) {
            return $this->linked_po_numbers;
        }
        if ($this->salesOrder) {
            return $this->salesOrder->order_number;
        }
        if ($this->commercialShipment) {
            return $this->commercialShipment->po_numbers;
        }

        return 'Direct Invoice';
    }
}
