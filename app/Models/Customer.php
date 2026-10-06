<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_code',
        'company_name',
        'trade_name',
        'gst_number',
        'pan_number',
        'credit_limit',
        'credit_limit_action',
        'payment_terms_days',
        'stage',
        'health_score',
        'status',
        'primary_contact_person',
        'primary_phone',
        'primary_email',
        'address_line',
        'city',
        'state',
        'state_code',
        'pincode',
        'country',
        'website',
        'notes',
        'total_revenue',
        'outstanding_amount',
        'last_order_date',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'total_revenue' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'last_order_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($customer) {
            if (Schema::hasColumn('customers', 'tenant_id') && ! $customer->tenant_id) {
                $customer->tenant_id = TenantManager::getTenantId();
            }
            if (empty($customer->customer_code)) {
                $customer->customer_code = static::generateNextCode($customer->tenant_id ?? null);
            }
        });
    }

    /**
     * Generate sequential, zero-padded customer code scoped to tenant (e.g. CUST-0001, CUST-0002).
     * Every new tenant starts at CUST-0001.
     */
    public static function generateNextCode(?int $tenantId = null): string
    {
        $query = static::withoutGlobalScopes();
        if ($tenantId && Schema::hasColumn('customers', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $codes = $query->whereNotNull('customer_code')->pluck('customer_code');

        $maxNum = 0;
        foreach ($codes as $code) {
            if (preg_match('/CUST[-_]?(\d+)/i', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;

        return 'CUST-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Alias accessor for legal name mapping to company_name
     */
    public function getLegalNameAttribute(): string
    {
        return $this->company_name ?? '';
    }

    /**
     * Alias mutator for legal name mapping to company_name
     */
    public function setLegalNameAttribute($value): void
    {
        $this->attributes['company_name'] = $value;
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function salesOrderItems(): HasManyThrough
    {
        return $this->hasManyThrough(SalesOrderItem::class, SalesOrder::class, 'customer_id', 'sales_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'customer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class, 'customer_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class, 'customer_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'customer_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(FollowupTask::class, 'customer_id');
    }

    public function followupTasks(): HasMany
    {
        return $this->hasMany(FollowupTask::class, 'customer_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'customer_id');
    }

    public function aiInsights(): HasMany
    {
        return $this->hasMany(AiInsight::class, 'customer_id');
    }

    public function getTotalOrderedQtyAttribute(): float
    {
        return (float) $this->salesOrderItems()->sum('order_qty');
    }

    public function getTotalSuppliedQtyAttribute(): float
    {
        return (float) $this->salesOrderItems()->sum('shipped_qty');
    }

    public function getTotalPendingQtyAttribute(): float
    {
        return (float) $this->salesOrderItems()->sum('balance_qty');
    }

    public function getFulfillmentPercentageAttribute(): float
    {
        $ordered = $this->total_ordered_qty;
        if ($ordered <= 0) {
            return 100.0;
        }

        return round(($this->total_supplied_qty / $ordered) * 100, 1);
    }

    public function priceOverrides(): HasMany
    {
        return $this->hasMany(PartyPriceOverride::class, 'customer_id');
    }

    /**
     * Evaluate customer exposure against credit limit.
     *
     * @return array{
     *     allowed: bool,
     *     action: string,
     *     credit_limit: float,
     *     total_exposure: float,
     *     exceeded_by: float,
     *     message: string
     * }
     */
    public function checkCreditLimit(float $additionalAmount = 0.0): array
    {
        $limit = (float) ($this->credit_limit ?? 0.0);
        $action = strtoupper($this->credit_limit_action ?? 'NONE');
        $currentOutstanding = (float) ($this->outstanding_amount ?? 0.0);
        $totalExposure = $currentOutstanding + $additionalAmount;

        if ($limit <= 0 || $action === 'NONE') {
            return [
                'allowed' => true,
                'action' => 'NONE',
                'credit_limit' => $limit,
                'total_exposure' => $totalExposure,
                'exceeded_by' => 0.0,
                'message' => 'No credit limit restriction configured.',
            ];
        }

        if ($totalExposure > $limit) {
            $exceededBy = $totalExposure - $limit;
            $formattedLimit = number_format($limit, 2);
            $formattedExceeded = number_format($exceededBy, 2);

            if ($action === 'BLOCK') {
                return [
                    'allowed' => false,
                    'action' => 'BLOCK',
                    'credit_limit' => $limit,
                    'total_exposure' => $totalExposure,
                    'exceeded_by' => $exceededBy,
                    'message' => "Order/Invoice blocked: Total exposure of ₹{$totalExposure} exceeds configured credit limit of ₹{$formattedLimit} by ₹{$formattedExceeded}.",
                ];
            }

            return [
                'allowed' => true,
                'action' => 'WARN',
                'credit_limit' => $limit,
                'total_exposure' => $totalExposure,
                'exceeded_by' => $exceededBy,
                'message' => "Credit warning: Total exposure of ₹{$totalExposure} exceeds credit limit of ₹{$formattedLimit} by ₹{$formattedExceeded}.",
            ];
        }

        return [
            'allowed' => true,
            'action' => $action,
            'credit_limit' => $limit,
            'total_exposure' => $totalExposure,
            'exceeded_by' => 0.0,
            'message' => 'Within credit limit.',
        ];
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->primary_phone;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->primary_email;
    }
}
