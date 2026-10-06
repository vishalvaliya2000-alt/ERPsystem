<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReminderLog extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_reminder_logs';

    protected $fillable = [
        'firm_id',
        'customer_id',
        'invoice_id',
        'reminder_type',
        'channel',
        'recipient_phone',
        'recipient_email',
        'sent_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
