<?php

namespace App\Services;

use App\Models\Tenant;

class TenantManager
{
    protected static ?Tenant $currentTenant = null;

    public static function getTenant(): ?Tenant
    {
        if (static::$currentTenant !== null) {
            return static::$currentTenant;
        }

        try {
            $user = auth()->user();
            if ($user && $user->tenant_id) {
                static::$currentTenant = Tenant::find($user->tenant_id);
                if (static::$currentTenant) {
                    return static::$currentTenant;
                }
            }

            // Fallback to Tenant 1
            static::$currentTenant = Tenant::first();
        } catch (\Throwable $e) {
            static::$currentTenant = null;
        }

        return static::$currentTenant;
    }

    public static function getTenantId(): ?int
    {
        try {
            $user = auth()->user();
            if ($user && $user->tenant_id) {
                return (int) $user->tenant_id;
            }

            $tenant = static::getTenant();

            return $tenant ? (int) $tenant->id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    public static function setTenant(?Tenant $tenant): void
    {
        static::$currentTenant = $tenant;
    }

    public static function setTenantId(int $id): void
    {
        static::$currentTenant = Tenant::find($id);
    }

    public static function clear(): void
    {
        static::$currentTenant = null;
    }
}
