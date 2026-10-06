<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantWhatsAppSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_whatsapp_settings';

    protected $fillable = [
        'firm_id',
        'provider',
        'phone_number_id',
        'access_token',
        'access_token_encrypted',
        'waba_id',
        'sender_phone_number',
        'is_active',
        'auto_send_invoices',
        'auto_send_shipments',
        'auto_send_receipts',
        'auto_send_reminders',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_send_invoices' => 'boolean',
        'auto_send_shipments' => 'boolean',
        'auto_send_receipts' => 'boolean',
        'auto_send_reminders' => 'boolean',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function getAccessTokenAttribute(): ?string
    {
        if (empty($this->access_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['access_token_encrypted'] = null;
        } else {
            $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
        }
    }
}
