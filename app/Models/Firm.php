<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Firm extends Model
{
    use LogsActivity;

    protected $fillable = [
        'company_id',
        'firm_name',
        'gstin',
        'state',
        'state_code',
        'pan',
        'address_line1',
        'address_line2',
        'city',
        'pincode',
        'email',
        'phone',
        'lut_arn',
        'iec_code',
        'fssai_number',
        'invoice_prefix',
        'quotation_prefix',
        'po_prefix',
        'shipment_prefix',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_branch',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('firms')
            ->logOnly([
                'firm_name', 'gstin', 'state', 'state_code', 'invoice_prefix',
                'quotation_prefix', 'bank_name', 'bank_account_number', 'bank_ifsc',
                'is_default', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function commercialShipments(): HasMany
    {
        return $this->hasMany(CommercialShipment::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->pincode,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Mark this firm as default (within its company or globally).
     */
    public function makeDefault(): void
    {
        static::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Resolves the 2-digit Indian State GST Code.
     */
    public function resolveStateCode(): string
    {
        if (! empty($this->state_code)) {
            return str_pad((string) $this->state_code, 2, '0', STR_PAD_LEFT);
        }

        if (! empty($this->gstin) && strlen($this->gstin) >= 2) {
            return substr($this->gstin, 0, 2);
        }

        return '24';
    }
}
