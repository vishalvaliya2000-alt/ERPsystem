<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        // Audited Super-Admin Impersonation from Central Portal
        if ($request->boolean('impersonate') && $request->filled('tenant_id')) {
            $tenantId = (string) $request->input('tenant_id');
            $centralConn = config('tenancy.database.central_connection', 'mysql');

            $validLog = DB::connection($centralConn)->table('impersonation_logs')
                ->where('tenant_id', $tenantId)
                ->where('started_at', '>=', now()->subMinutes(10))
                ->latest('started_at')
                ->first();

            if ($validLog) {
                $user = User::where('is_active', true)->first();

                if ($user) {
                    Auth::login($user);
                    $request->session()->regenerate();
                    session(['is_impersonating' => true]);

                    return redirect()->route('dashboard')->with('info', "Super-Admin Impersonation active as {$user->name}.");
                }
            }
        }

        return view('auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact the administrator.',
                ]);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, '.$user->name.'!');
        }

        return back()->withInput($request->only('email', 'remember'))->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_name' => ['required', 'string', 'max:150'],
            'industry' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
        ]);

        // Generate default document prefixes based on company name
        $rawPrefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $validated['company_name']));
        $prefix = substr($rawPrefix, 0, 3) ?: 'ERP';

        // 1. Create Tenant (New Business Workspace) - Zero Dummy Data
        $tenant = Tenant::create([
            'name' => trim($validated['company_name']),
            'slug' => Str::slug($validated['company_name']).'-'.rand(100, 999),
            'tagline' => 'GST Billing, Inventory & Operations',
            'industry' => $validated['industry'],
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'tax_id_label' => 'GSTIN',
            'tax_id_number' => ! empty($validated['gstin']) ? strtoupper(trim($validated['gstin'])) : null,
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'city' => ! empty($validated['city']) ? trim($validated['city']) : null,
            'state' => ! empty($validated['state']) ? trim($validated['state']) : 'Gujarat',
            'country' => 'India',
            'invoice_prefix' => $prefix.'-INV',
            'quotation_prefix' => $prefix.'-QUO',
            'po_prefix' => $prefix.'-PO',
            'shipment_prefix' => $prefix.'-SHP',
            'plan' => 'GROWTH_ENTERPRISE',
            'is_active' => true,
        ]);

        // 2. Create User Account for Business Owner
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'password' => Hash::make($validated['password']),
            'role' => 'OWNER',
            'designation' => 'Business Owner / Managing Director',
            'is_active' => true,
        ]);

        // Attach to pivot table for consistency
        $tenant->users()->attach($user->id, [
            'role' => 'OWNER',
            'is_default' => true,
        ]);

        // 3. Authenticate User immediately
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "🎉 Welcome to your new business ERP, {$user->name}! Workspace '{$tenant->name}' is ready.");
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out securely.');
    }

    public function profile()
    {
        $user = Auth::user();

        return view('auth.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
