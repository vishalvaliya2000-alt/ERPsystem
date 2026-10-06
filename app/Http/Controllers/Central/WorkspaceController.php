<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function show(): View
    {
        $tenantRootDomain = config('app.tenant_domain', 'erpsaas.com');

        return view('central.find-workspace', compact('tenantRootDomain'));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string'],
        ]);

        $raw = strtolower(trim($validated['workspace']));
        $cleaned = preg_replace('#^https?://#', '', $raw);

        if (str_contains($cleaned, '@')) {
            // Lookup by exact email
            $tenant = Tenant::where('owner_email', $cleaned)->first();
        } else {
            // Lookup by subdomain, name, or domain
            $subdomain = explode('.', $cleaned)[0];
            $tenant = Tenant::where('id', $subdomain)
                ->orWhere('id', $cleaned)
                ->orWhere('name', 'like', "%{$subdomain}%")
                ->orWhere('owner_email', $cleaned)
                ->first();
        }

        if (! $tenant) {
            return back()->withInput()->withErrors([
                'workspace' => "We could not find an active workspace matching '{$validated['workspace']}'. Please check your spelling or register a new workspace.",
            ]);
        }

        $domain = $tenant->primary_domain ?? ($tenant->id.'.'.config('app.tenant_domain', 'erpsaas.com'));
        $port = $request->getPort();
        $portSuffix = ($port && ! in_array($port, [80, 443])) ? ":{$port}" : '';
        $scheme = $request->getScheme();

        return redirect()->away("{$scheme}://{$domain}{$portSuffix}/login");
    }
}
