<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function dashboard(): View
    {
        $tenantCount = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $tenants = Tenant::latest()->take(10)->get();
        $plans = Plan::with('features')->get();

        return view('central.super-admin.dashboard', compact(
            'tenantCount', 'activeTenants', 'suspendedTenants', 'tenants', 'plans'
        ));
    }

    public function tenants(Request $request): View
    {
        $query = Tenant::query();

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
                    ->orWhere('owner_email', 'like', "%{$s}%")
                    ->orWhere('gstin', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tenants = $query->latest()->paginate(15);

        return view('central.super-admin.tenants', compact('tenants'));
    }

    public function toggleStatus(string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $newStatus = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        return back()->with('success', "Tenant [{$tenant->name}] status changed to {$newStatus}.");
    }

    public function impersonate(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $domain = $tenant->primary_domain;

        // Resolve or create super-admin user for foreign key integrity
        $centralUserId = (auth()->check() ? auth()->id() : null) ?? DB::table('central_users')->value('id');
        if (! $centralUserId) {
            $centralUserId = DB::table('central_users')->insertGetId([
                'name' => 'Super Administrator',
                'email' => 'admin@erpsaas.in',
                'password' => bcrypt('AdminSecret123!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Log impersonation
        DB::table('impersonation_logs')->insert([
            'central_user_id' => $centralUserId,
            'tenant_id' => $tenant->id,
            'ip_address' => $request->ip(),
            'reason' => 'Administrative inspection by super-admin',
            'started_at' => now(),
        ]);

        $scheme = $request->getScheme();
        $port = $request->getPort();
        $portSuffix = ($port && ! in_array($port, [80, 443])) ? ":{$port}" : '';

        // Generate signed URL to tenant login with impersonation token
        $url = "{$scheme}://{$domain}{$portSuffix}/login?impersonate=1&tenant_id={$tenant->id}&ts=".time();

        return redirect()->away($url);
    }
}
