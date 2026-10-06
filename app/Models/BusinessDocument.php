<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'document_type',
        'document_number',
        'document_date',
        'due_date',
        'customer_id',
        'sales_order_id',
        'commercial_shipment_id',
        'invoice_id',
        'total_amount',
        'taxable_amount',
        'tax_amount',
        'currency',
        'payment_terms',
        'delivery_location',
        'status',
        'notes',
        'extracted_metadata',
        'current_version',
        'created_by_user_id',
    ];

    protected $casts = [
        'document_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'extracted_metadata' => 'array',
        'current_version' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }

    public function commercialShipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'document_id')->where('is_active', true);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('documents.download', $this->id);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('documents.preview', $this->id);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'PO' => 'Purchase Order',
            'INVOICE' => 'Tax Invoice',
            'LR' => 'Transport LR',
            default => 'Business Document',
        };
    }

    public function getTypeBadgeColorAttribute(): string
    {
        return match ($this->document_type) {
            'PO' => 'bg-blue-50 text-blue-800 border-blue-200',
            'INVOICE' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'LR' => 'bg-purple-50 text-purple-800 border-purple-200',
            default => 'bg-neutral-100 text-neutral-800 border-neutral-200',
        };
    }

    /**
     * Resolves the full connected ERP chain (PO -> SalesOrder -> Shipment/LR -> Invoice -> Payments)
     */
    public function getConnectedChainAttribute(): array
    {
        // 1. Customer
        $customer = $this->customer;

        // 2. Sales Order / PO
        $salesOrder = $this->salesOrder;
        if (! $salesOrder && $this->invoice?->salesOrder) {
            $salesOrder = $this->invoice->salesOrder;
        }
        if (! $salesOrder && $this->shipment?->salesOrder) {
            $salesOrder = $this->shipment->salesOrder;
        }

        // 3. Commercial Shipment / LR
        $shipment = $this->shipment;
        if (! $shipment && $this->invoice?->effective_shipment) {
            $shipment = $this->invoice->effective_shipment;
        }
        if (! $shipment && $salesOrder) {
            $shipment = CommercialShipment::whereHas('items.salesOrderItem', function ($q) use ($salesOrder) {
                $q->where('sales_order_id', $salesOrder->id);
            })->latest('shipment_date')->first();
        }

        // 4. Invoice
        $invoice = $this->invoice;
        if (! $invoice && $salesOrder) {
            $invoice = Invoice::where('sales_order_id', $salesOrder->id)->latest('invoice_date')->first();
        }
        if (! $invoice && $shipment) {
            $invoice = Invoice::where('commercial_shipment_id', $shipment->id)->latest('invoice_date')->first();
        }

        // 5. Payment Receipts
        $receipts = [];
        $balanceDue = 0.0;
        $totalReceived = 0.0;
        if ($invoice) {
            $balanceDue = (float) $invoice->balance_due;
            $totalReceived = (float) $invoice->amount_received;
            $receipts = $invoice->receipts()->orderByDesc('receipt_date')->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'amount' => (float) $r->amount_received,
                    'payment_mode' => $r->payment_mode,
                    'reference_number' => $r->reference_number,
                    'receipt_date' => $r->receipt_date?->format('d M Y'),
                ];
            })->toArray();
        }

        return [
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->company_name,
                'code' => $customer->customer_code,
                'contact_person' => $customer->primary_contact_person,
                'phone' => $customer->primary_phone,
                'city' => $customer->city,
                'state' => $customer->state,
            ] : null,
            'sales_order' => $salesOrder ? [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'po_number' => $salesOrder->po_number ?: ($this->document_type === 'PO' ? $this->document_number : null),
                'order_date' => $salesOrder->order_date?->format('d M Y'),
                'total_amount' => (float) $salesOrder->total_amount,
                'status' => $salesOrder->status,
                'items_count' => $salesOrder->items()->count(),
            ] : null,
            'shipment' => $shipment ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'lr_number' => $shipment->lr_number,
                'transporter' => $shipment->transporter,
                'vehicle_number' => $shipment->vehicle_number,
                'shipment_date' => $shipment->shipment_date?->format('d M Y'),
                'status' => $shipment->status,
                'destination' => $shipment->destination,
            ] : null,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('d M Y'),
                'due_date' => $invoice->due_date?->format('d M Y'),
                'total_amount' => (float) $invoice->total_amount,
                'amount_received' => (float) $invoice->amount_received,
                'balance_due' => (float) $invoice->balance_due,
                'status' => $invoice->status,
            ] : null,
            'payments' => [
                'receipts' => $receipts,
                'total_received' => $totalReceived,
                'balance_due' => $balanceDue,
            ],
        ];
    }
}
