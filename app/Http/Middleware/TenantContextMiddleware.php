<?php

namespace App\Http\Middleware;

use App\Services\CompanyFirmContext;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant() ?? TenantManager::getTenant();

        if ($tenant) {
            // Share active tenant globally across all Blade views
            View::share('currentTenant', $tenant);

            if (tenancy()->initialized) {
                try {
                    $firm = CompanyFirmContext::getFirm();
                    $company = CompanyFirmContext::getCompany();
                    $availableCompanies = CompanyFirmContext::availableCompanies();

                    View::share('currentFirm', $firm);
                    View::share('currentCompany', $company);
                    View::share('availableCompanies', $availableCompanies);
                } catch (\Throwable $e) {
                    // Suppress if tenant database is still being provisioned/migrated
                }
            }
        }

        return $next($request);
    }
}
