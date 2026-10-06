<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Schema;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // In database-per-tenant architecture, tenancy is strictly enforced at database connection level.
        // We only apply column scoping if the table actually contains a legacy tenant_id column.
        static::creating(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'tenant_id')) {
                if (! $model->tenant_id) {
                    $tenantId = TenantManager::getTenantId();
                    if ($tenantId) {
                        $model->tenant_id = $tenantId;
                    }
                }
            }
        });
    }

    public function tenant()
    {
        if (Schema::hasColumn($this->getTable(), 'tenant_id')) {
            return $this->belongsTo(Tenant::class, 'tenant_id');
        }

        return $this->belongsTo(Tenant::class, 'id')->withDefault(function () {
            return tenant() ?: TenantManager::getTenant();
        });
    }

    public function getTenantAttribute()
    {
        return tenant() ?: TenantManager::getTenant();
    }
}
