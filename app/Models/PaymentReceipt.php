<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'receipt_number',
        'invoice_id',
        'sales_order_id',
        'receipt_type',
        'customer_id',
        'receipt_date',
        'amount_received',
        'payment_mode',
        'reference_number',
        'remarks',
    ];

    protected $casts = [
        'receipt_date' => 'datetime',
        'amount_received' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
