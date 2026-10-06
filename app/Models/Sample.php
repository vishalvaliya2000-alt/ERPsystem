<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sample extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sample_number',
        'customer_id',
        'contact_id',
        'product_id',
        'quantity',
        'uom',
        'batch_number',
        'sample_type',
        'courier_provider',
        'awb_number',
        'tracking_url',
        'delivery_status',
        'delivered_at',
        'feedback_rating',
        'customer_feedback',
        'remarks',
        'trial_status',
        'trial_result',
        'next_action',
        'next_action_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'delivered_at' => 'datetime',
        'next_action_date' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function courierShipment(): HasOne
    {
        return $this->hasOne(CourierShipment::class, 'sample_id');
    }
}
