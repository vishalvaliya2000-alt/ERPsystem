<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'firm_id',
        'order_number',
        'po_number',
        'po_date',
        'customer_id',
        'order_date',
        'expected_dispatch_date',
        'actual_dispatch_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'advance_required',
        'advance_received',
        'balance_amount',
        'payment_terms',
        'status',
        'revision_number',
        'last_revised_at',
        'last_revision_reason',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'po_date' => 'datetime',
        'expected_dispatch_date' => 'datetime',
        'actual_dispatch_date' => 'datetime',
        'last_revised_at' => 'datetime',
        'revision_number' => 'integer',
        'firm_id' => 'integer',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'advance_required' => 'decimal:2',
        'advance_received' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    protected $appends = [
        'order_date_formatted',
    ];

    public function getOrderDateFormattedAttribute(): string
    {
        return $this->order_date ? $this->order_date->format('Y-m-d') : '';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SalesOrderRevision::class, 'sales_order_id')->orderByDesc('revision_number');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class, 'sales_order_id');
    }

    public function poDocument(): HasOne
    {
        return $this->hasOne(BusinessDocument::class, 'sales_order_id')
            ->ofMany(['id' => 'max'], function ($q) {
                $q->where('document_type', 'PO');
            });
    }

    public function createRevisionSnapshot(string $reason, ?string $revisedByName = null, ?int $revisedByUserId = null): SalesOrderRevision
    {
        $this->loadMissing(['customer', 'items.product']);

        $itemsSnapshot = $this->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->product_name ?? 'Unknown Product',
                'product_code' => $item->product?->product_code ?? '',
                'order_qty' => (float) $item->order_qty,
                'rate' => (float) $item->rate,
                'order_value' => (float) $item->order_value,
                'shipped_qty' => (float) $item->shipped_qty,
                'balance_qty' => (float) $item->balance_qty,
                'status' => $item->status,
            ];
        })->toArray();

        $snapshotData = [
            'order_number' => $this->order_number,
            'po_number' => $this->po_number,
            'po_date' => $this->po_date?->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->company_name ?? 'N/A',
            'order_date' => $this->order_date?->format('Y-m-d H:i:s'),
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'payment_terms' => $this->payment_terms,
            'status' => $this->status,
            'notes' => $this->notes,
            'total_qty' => (float) $this->items->sum('order_qty'),
            'items' => $itemsSnapshot,
        ];

        $currentRev = (int) ($this->revision_number ?? 0);

        $revision = SalesOrderRevision::create([
            'tenant_id' => $this->tenant_id,
            'sales_order_id' => $this->id,
            'revision_number' => $currentRev,
            'revision_reason' => $reason,
            'revised_by_user_id' => $revisedByUserId,
            'revised_by_name' => $revisedByName ?? 'User',
            'snapshot' => $snapshotData,
        ]);

        $this->update([
            'revision_number' => $currentRev + 1,
            'last_revised_at' => now(),
            'last_revision_reason' => $reason,
        ]);

        return $revision;
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(CommercialShipment::class, 'sales_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'sales_order_id');
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'sales_order_id')->orderByDesc('receipt_date');
    }

    public function advanceReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'sales_order_id')
            ->where('receipt_type', 'PO_ADVANCE')
            ->orderByDesc('receipt_date');
    }

    /**
     * Unadjusted advance available for offset against future invoices.
     */
    public function unadjustedAdvanceAmount(): float
    {
        // Advance received against this PO minus advance amounts already offset against invoices
        $alreadyAdjusted = (float) $this->paymentReceipts()
            ->where('payment_mode', 'ADVANCE_OFFSET')
            ->sum('amount_received');

        $unadjusted = (float) $this->advance_received - $alreadyAdjusted;

        return max(0, $unadjusted);
    }

    /**
     * Atomically record an advance payment against this sales order.
     */
    public function recordAdvance(float $amount, string $paymentMode, ?string $refNumber = null, ?string $remarks = null, $receiptDate = null): PaymentReceipt
    {
        $receiptNumber = 'ADV-'.date('Ymd').'-'.rand(100, 999);
        $date = $receiptDate ? Carbon::parse($receiptDate) : Carbon::now();

        $receipt = PaymentReceipt::create([
            'tenant_id' => $this->tenant_id,
            'receipt_number' => $receiptNumber,
            'sales_order_id' => $this->id,
            'invoice_id' => null,
            'receipt_type' => 'PO_ADVANCE',
            'customer_id' => $this->customer_id,
            'receipt_date' => $date,
            'amount_received' => $amount,
            'payment_mode' => $paymentMode,
            'reference_number' => $refNumber,
            'remarks' => $remarks ?: "Advance received against PO #{$this->order_number}",
        ]);

        $newAdvance = (float) $this->advance_received + $amount;
        $newBalance = max(0, (float) $this->total_amount - $newAdvance);

        $updates = [
            'advance_received' => $newAdvance,
            'balance_amount' => $newBalance,
        ];

        // If status was ADVANCE_PENDING and advance meets or exceeds required advance (or if no specific required advance was set)
        if ($this->status === 'ADVANCE_PENDING') {
            $required = (float) $this->advance_required;
            if ($required <= 0 || $newAdvance >= $required) {
                $updates['status'] = 'CONFIRMED';
            }
        }

        $this->update($updates);

        // Log Activity
        ActivityLog::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'activity_type' => 'PAYMENT',
            'title' => 'Advance Received ('.formatINR($amount).')',
            'description' => "Received {$paymentMode} (Ref: {$refNumber}) against PO {$this->order_number}.",
            'occurred_at' => $date,
        ]);

        return $receipt;
    }

    public function totalOrderedQty(): float
    {
        return (float) $this->items()->sum('order_qty');
    }

    public function totalShippedQty(): float
    {
        return (float) $this->items()->sum('shipped_qty');
    }

    public function totalBalanceQty(): float
    {
        return (float) $this->items()->sum('balance_qty');
    }

    public function isFullyDelivered(): bool
    {
        return $this->totalBalanceQty() <= 0;
    }

    public function totalInvoicedAmount(): float
    {
        return (float) $this->invoices()->sum('subtotal');
    }

    public function remainingInvoicableAmount(): float
    {
        return max(0, (float) $this->total_amount - $this->totalInvoicedAmount());
    }

    public function isFullyInvoiced(): bool
    {
        return $this->remainingInvoicableAmount() <= 0 && $this->total_amount > 0;
    }
}
