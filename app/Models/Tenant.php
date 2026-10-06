<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'owner_name',
            'owner_email',
            'owner_phone',
            'gstin',
            'plan',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    public function getSubdomainAttribute(): string
    {
        return $this->id;
    }

    public function getPrimaryDomainAttribute(): ?string
    {
        return $this->attributes['primary_domain']
            ?? ($this->data['primary_domain'] ?? null)
            ?? $this->domains()->first()?->domain;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line ?? null,
            $this->city ?? null,
            $this->state ?? 'Gujarat',
            $this->pincode ?? null,
            $this->country ?? 'India',
        ]);

        return implode(', ', $parts);
    }

    public function isExportCompany(): bool
    {
        return (bool) ($this->data['is_export_company'] ?? false);
    }

    public function users()
    {
        return User::query();
    }
}
