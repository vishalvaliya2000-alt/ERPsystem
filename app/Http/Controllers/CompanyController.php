<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Firm;
use App\Services\CompanyFirmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();
        $companies = Company::with('firms')->orderByDesc('is_default')->get();
        $currentFirm = CompanyFirmContext::getFirm();
        $currentCompany = CompanyFirmContext::getCompany();

        return view('organization.companies', compact('tenant', 'companies', 'currentFirm', 'currentCompany'));
    }

    public function switchContext(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firm_id' => ['required', 'exists:firms,id'],
        ]);

        $firm = Firm::with('company')->findOrFail($validated['firm_id']);

        if (! $firm->is_active || ! $firm->company->is_active) {
            return back()->with('error', 'Cannot switch to an inactive firm or company.');
        }

        CompanyFirmContext::setFirm($firm);

        return back()->with('success', "✓ Active context switched to {$firm->firm_name} ({$firm->company->legal_name}).");
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'cin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:150'],
        ]);

        DB::beginTransaction();
        try {
            $isFirst = Company::count() === 0;

            $company = Company::create([
                'legal_name' => trim($validated['legal_name']),
                'trade_name' => ! empty($validated['trade_name']) ? trim($validated['trade_name']) : trim($validated['legal_name']),
                'cin' => ! empty($validated['cin']) ? strtoupper(trim($validated['cin'])) : null,
                'pan' => ! empty($validated['pan']) ? strtoupper(trim($validated['pan'])) : null,
                'website' => $validated['website'] ?? null,
                'is_default' => $isFirst,
                'is_active' => true,
            ]);

            // Create initial main unit firm for this company
            $firm = Firm::create([
                'company_id' => $company->id,
                'firm_name' => $company->trade_name.' (Main Unit)',
                'state' => 'Gujarat',
                'state_code' => '24',
                'pan' => $company->pan,
                'invoice_prefix' => 'INV',
                'quotation_prefix' => 'QUO',
                'po_prefix' => 'PO',
                'shipment_prefix' => 'SHP',
                'is_default' => true,
                'is_active' => true,
            ]);

            activity('companies')
                ->performedOn($company)
                ->causedBy(auth()->user())
                ->log("Created company {$company->legal_name} with primary branch {$firm->firm_name}");

            DB::commit();

            return back()->with('success', "✓ Company {$company->legal_name} created with primary unit.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to create company: '.$e->getMessage());
        }
    }

    public function updateCompany(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'cin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:150'],
        ]);

        $company->update([
            'legal_name' => trim($validated['legal_name']),
            'trade_name' => $validated['trade_name'] ? trim($validated['trade_name']) : $company->trade_name,
            'cin' => $validated['cin'] ? strtoupper(trim($validated['cin'])) : null,
            'pan' => $validated['pan'] ? strtoupper(trim($validated['pan'])) : null,
            'website' => $validated['website'] ?? null,
        ]);

        activity('companies')
            ->performedOn($company)
            ->causedBy(auth()->user())
            ->log("Updated company details for {$company->legal_name}");

        return back()->with('success', "✓ Company {$company->legal_name} updated successfully.");
    }

    public function setDefaultCompany(Company $company): RedirectResponse
    {
        if (! $company->is_active) {
            return back()->with('error', 'Cannot set an inactive company as default.');
        }

        $company->makeDefault();
        CompanyFirmContext::setCompany($company);

        activity('companies')
            ->performedOn($company)
            ->causedBy(auth()->user())
            ->log("Set {$company->legal_name} as default company");

        return back()->with('success', "✓ {$company->legal_name} is now the default organization company.");
    }

    public function toggleCompanyStatus(Company $company): RedirectResponse
    {
        if ($company->is_active) {
            $otherActive = Company::where('id', '!=', $company->id)->where('is_active', true)->count();
            if ($otherActive === 0) {
                return back()->with('error', 'Cannot deactivate the sole active company.');
            }
        }

        $company->is_active = ! $company->is_active;
        $company->save();

        if (! $company->is_active) {
            CompanyFirmContext::flushCache();
        }

        $status = $company->is_active ? 'Activated' : 'Deactivated';

        activity('companies')
            ->performedOn($company)
            ->causedBy(auth()->user())
            ->log("{$status} company {$company->legal_name}");

        return back()->with('success', "✓ Company {$company->legal_name} {$status}.");
    }

    public function storeFirm(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'firm_name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'state' => ['required', 'string', 'max:100'],
            'state_code' => ['required', 'string', 'max:5'],
            'pan' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'quotation_prefix' => ['required', 'string', 'max:20'],
            'po_prefix' => ['required', 'string', 'max:20'],
            'shipment_prefix' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'lut_arn' => ['nullable', 'string', 'max:100'],
            'iec_code' => ['nullable', 'string', 'max:50'],
            'fssai_number' => ['nullable', 'string', 'max:50'],
        ]);

        $isFirst = $company->firms()->count() === 0;

        $firm = $company->firms()->create(array_merge($validated, [
            'pan' => $validated['pan'] ? strtoupper(trim($validated['pan'])) : $company->pan,
            'gstin' => $validated['gstin'] ? strtoupper(trim($validated['gstin'])) : null,
            'is_default' => $isFirst,
            'is_active' => true,
        ]));

        activity('firms')
            ->performedOn($firm)
            ->causedBy(auth()->user())
            ->log("Added branch/firm {$firm->firm_name} under {$company->legal_name}");

        return back()->with('success', "✓ Branch/Firm {$firm->firm_name} added successfully.");
    }

    public function updateFirm(Request $request, Firm $firm): RedirectResponse
    {
        $validated = $request->validate([
            'firm_name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'state' => ['required', 'string', 'max:100'],
            'state_code' => ['required', 'string', 'max:5'],
            'pan' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'quotation_prefix' => ['required', 'string', 'max:20'],
            'po_prefix' => ['required', 'string', 'max:20'],
            'shipment_prefix' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'lut_arn' => ['nullable', 'string', 'max:100'],
            'iec_code' => ['nullable', 'string', 'max:50'],
            'fssai_number' => ['nullable', 'string', 'max:50'],
        ]);

        if (! empty($validated['gstin'])) {
            $validated['gstin'] = strtoupper(trim($validated['gstin']));
        }
        if (! empty($validated['pan'])) {
            $validated['pan'] = strtoupper(trim($validated['pan']));
        }

        $firm->update($validated);

        activity('firms')
            ->performedOn($firm)
            ->causedBy(auth()->user())
            ->log("Updated firm details for {$firm->firm_name}");

        return back()->with('success', "✓ Branch/Firm {$firm->firm_name} updated successfully.");
    }

    public function setDefaultFirm(Firm $firm): RedirectResponse
    {
        if (! $firm->is_active) {
            return back()->with('error', 'Cannot set an inactive firm as default.');
        }

        $firm->makeDefault();
        CompanyFirmContext::setFirm($firm);

        activity('firms')
            ->performedOn($firm)
            ->causedBy(auth()->user())
            ->log("Set {$firm->firm_name} as default firm");

        return back()->with('success', "✓ {$firm->firm_name} is now the default operational unit.");
    }

    public function toggleFirmStatus(Firm $firm): RedirectResponse
    {
        if ($firm->is_active) {
            $otherActive = Firm::where('id', '!=', $firm->id)->where('is_active', true)->count();
            if ($otherActive === 0) {
                return back()->with('error', 'Cannot deactivate the sole active firm.');
            }
        }

        $firm->is_active = ! $firm->is_active;
        $firm->save();

        if (! $firm->is_active && (int) session('active_firm_id') === (int) $firm->id) {
            CompanyFirmContext::flushCache();
            session()->forget('active_firm_id');
        }

        $status = $firm->is_active ? 'Activated' : 'Deactivated';

        activity('firms')
            ->performedOn($firm)
            ->causedBy(auth()->user())
            ->log("{$status} firm {$firm->firm_name}");

        return back()->with('success', "✓ Firm {$firm->firm_name} {$status}.");
    }
}
