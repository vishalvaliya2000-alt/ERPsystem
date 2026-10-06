<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::orderBy('company_name')->get();

        return view('vendors.index', compact('vendors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:150',
            'vendor_code' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'gstin' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'payment_terms_days' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $code = $validated['vendor_code'] ?: ('VEN-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $validated['company_name']), 0, 3)).'-'.rand(100, 999));

        DB::beginTransaction();
        try {
            $vendor = Vendor::create([
                'vendor_code' => $code,
                'company_name' => $validated['company_name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? 'Gujarat',
                'payment_terms_days' => $validated['payment_terms_days'] ?? 15,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();

            return back()->with('success', "✓ Vendor '{$vendor->company_name}' added successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create vendor: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to add vendor: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'gstin' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'payment_terms_days' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $vendor->update($validated);
            DB::commit();

            return back()->with('success', "✓ Vendor '{$vendor->company_name}' updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update vendor #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update vendor: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $name = $vendor->company_name;

        DB::beginTransaction();
        try {
            $vendor->delete();
            DB::commit();

            return back()->with('success', "✓ Vendor '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete vendor #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete vendor: '.$e->getMessage());
        }
    }
}
