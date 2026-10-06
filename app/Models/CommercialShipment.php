<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Services\TransporterTrackingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CommercialShipment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'firm_id',
        'shipment_number',
        'sales_order_id',
        'shipment_date',
        'transporter',
        'lr_number',
        'vehicle_number',
        'destination',
        'delivery_type',
        'freight_payment_type',
        'freight_amount',
        'status',
        'expected_delivery_date',
        'actual_delivery_date',
        'notes',
        'proof_document_url',
    ];

    protected $casts = [
        'shipment_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'firm_id' => 'integer',
        'freight_amount' => 'decimal:2',
    ];

    protected $appends = [
        'shipment_date_formatted',
    ];

    public function getShipmentDateFormattedAttribute(): string
    {
        return $this->shipment_date ? $this->shipment_date->format('Y-m-d') : '';
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommercialShipmentItem::class, 'commercial_shipment_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'commercial_shipment_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class, 'commercial_shipment_id');
    }

    public function lrDocument(): HasOne
    {
        return $this->hasOne(BusinessDocument::class, 'commercial_shipment_id')
            ->ofMany(['id' => 'max'], function ($q) {
                $q->where('document_type', 'LR');
            });
    }

    public function ewayBill(): HasOne
    {
        return $this->hasOne(EwayBill::class, 'commercial_shipment_id')->latestOfMany();
    }

    public function ewayBills(): HasMany
    {
        return $this->hasMany(EwayBill::class, 'commercial_shipment_id');
    }

    public function getDistinctOrders(): Collection
    {
        return $this->items
            ->map(fn ($it) => $it->salesOrderItem?->salesOrder)
            ->filter()
            ->unique('id');
    }

    public function getPoNumbersAttribute(): string
    {
        $orders = $this->getDistinctOrders();
        if ($orders->isNotEmpty()) {
            return $orders->pluck('order_number')->join(', ');
        }

        return $this->salesOrder?->order_number ?? '—';
    }

    public function getCustomerNamesAttribute(): string
    {
        $orders = $this->getDistinctOrders();
        if ($orders->isNotEmpty()) {
            return $orders->map(fn ($o) => $o->customer?->company_name)->filter()->unique()->join(', ');
        }

        return $this->salesOrder?->customer?->company_name ?? '—';
    }

    public function getMaterialValueAttribute(): float
    {
        return (float) $this->items->sum('total_value');
    }

    public function getTotalConsignmentValueAttribute(): float
    {
        $matVal = $this->material_value;
        if ($this->freight_payment_type === 'PAID' && $this->freight_amount > 0) {
            return $matVal + (float) $this->freight_amount;
        }

        return $matVal;
    }

    public function getInvoicedMaterialAmountAttribute(): float
    {
        return (float) $this->invoices()->sum('material_subtotal');
    }

    public function getRemainingInvoicableMaterialAmountAttribute(): float
    {
        $matVal = $this->material_value;
        $invoiced = (float) $this->invoices()->sum('material_subtotal');

        return max(0, $matVal - $invoiced);
    }

    public function isFullyInvoiced(): bool
    {
        return $this->remaining_invoicable_material_amount <= 0 && $this->material_value > 0;
    }

    public function getLrUrlAttribute(): ?string
    {
        if (! empty($this->proof_document_url)) {
            if (str_starts_with($this->proof_document_url, 'http')) {
                return $this->proof_document_url;
            }

            return asset('storage/'.$this->proof_document_url);
        }

        return null;
    }

    public function getTrackingUrlAttribute(): ?string
    {
        return TransporterTrackingService::getTrackingUrl($this->transporter, $this->lr_number);
    }
}
