<?php

namespace App\Scopes;

use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantManager::getTenantId();
        if ($tenantId) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
