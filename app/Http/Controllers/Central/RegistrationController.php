<?php

namespace App\Http\Controllers\Central;

use App\Actions\ProvisionTenantAction;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SubdomainValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function show(): View
    {
        $plans = Plan::where('is_active', true)->get();
        $tenantRootDomain = config('app.tenant_domain', 'erpsaas.com');

        return view('central.register', compact('plans', 'tenantRootDomain'));
    }

    public function checkSubdomain(Request $request): JsonResponse
    {
        $subdomain = (string) $request->input('subdomain', '');
        $result = SubdomainValidator::isAvailable($subdomain);

        $tenantRootDomain = config('app.tenant_domain', 'erpsaas.com');
        $host = $request->getHost();
        $base = (str_ends_with($host, 'localhost') || $host === '127.0.0.1') ? 'localhost' : $tenantRootDomain;

        $result['preview'] = ! empty($result['subdomain']) ? "https://{$result['subdomain']}.{$base}" : '';

        return response()->json($result);
    }

    public function store(Request $request, ProvisionTenantAction $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'subdomain' => ['required', 'string', 'min:3', 'max:30'],
            'owner_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'plan' => ['nullable', 'string', 'in:Standard,Premium'],
            'terms' => ['accepted'],
        ]);

        $tenant = $provisioner->execute([
            'name' => $validated['business_name'],
            'subdomain' => $validated['subdomain'],
            'owner_name' => $validated['owner_name'],
            'owner_email' => $validated['email'],
            'owner_phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'gstin' => $validated['gstin'] ?? null,
            'plan' => $validated['plan'] ?? 'Standard',
        ]);

        $domain = $tenant->primary_domain;
        $port = $request->getPort();
        $portSuffix = ($port && ! in_array($port, [80, 443])) ? ":{$port}" : '';
        $scheme = $request->getScheme();

        $redirectUrl = "{$scheme}://{$domain}{$portSuffix}/login?registered=1";

        return redirect()->away($redirectUrl);
    }
}
