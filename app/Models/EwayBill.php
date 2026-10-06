<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EwayBill extends Model
{
    use BelongsToTenant;

    protected $table = 'eway_bills';

    protected $fillable = [
        'commercial_shipment_id',
        'firm_id',
        'eway_bill_number',
        'generated_at',
        'valid_until',
        'status',
        'doc_type',
        'supply_type',
        'request_payload',
        'response_payload',
        'qr_code_path',
        'cancel_reason',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'valid_until' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CommercialShipment::class, 'commercial_shipment_id');
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function isValid(): bool
    {
        return $this->status === 'ACTIVE' && $this->valid_until && $this->valid_until->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status !== 'CANCELLED';
    }

    public function canBeCancelled(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        // Under Indian E-way bill rules, cancel is permitted within 24 hours of generation
        return $this->generated_at && $this->generated_at->diffInHours(now()) <= 24;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }
}
