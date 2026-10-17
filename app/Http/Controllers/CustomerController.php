<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Product;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['contacts', 'salesOrders.items.product', 'invoices', 'followups', 'aiInsights'])
            ->orderByDesc('created_at')
            ->get();

        $tenantId = TenantManager::getTenantId();
        $nextCustomerCode = Customer::generateNextCode($tenantId);

        return view('customers.index', compact('customers', 'nextCustomerCode'));
    }

    public function show($id)
    {
        $customer = Customer::with([
            'contacts',
            'salesOrders' => function ($q) {
                $q->with([
                    'items.product',
                    'shipments.items.salesOrderItem.product',
                    'shipments.lrDocument.activeVersion',
                    'poDocument.activeVersion',
                    'documents.activeVersion',
                ])->orderByDesc('order_date');
            },
            'invoices' => function ($q) {
                $q->with([
                    'receipts',
                    'invoiceDocument.activeVersion',
                    'documents.activeVersion',
                ])->orderByDesc('invoice_date');
            },
            'quotations.items.product',
            'samples.product',
            'documents' => function ($q) {
                $q->with(['activeVersion', 'salesOrder', 'commercialShipment', 'invoice'])->orderByDesc('document_date');
            },
            'followups' => fn ($q) => $q->orderBy('due_date'),
            'activities' => fn ($q) => $q->orderByDesc('occurred_at'),
            'aiInsights',
        ])->findOrFail($id);

        $products = Product::where('is_active', true)->get();

        return view('customers.show', compact('customer', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'primary_contact_person' => 'nullable|string|max:255',
            'primary_phone' => 'nullable|string|max:30',
            'primary_email' => 'nullable|email|max:255',
            'address_line' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_limit_action' => 'nullable|string|in:WARN,BLOCK',
            'payment_terms_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $legalName = trim($validated['legal_name'] ?? ($validated['company_name'] ?? ''));
        if (empty($legalName)) {
            return back()->withInput()->withErrors(['company_name' => 'Legal Name of Business is required.']);
        }

        $tradeName = ! empty($validated['trade_name']) ? trim($validated['trade_name']) : null;

        if (empty($request->confirm_duplicate)) {
            $duplicateQuery = Customer::where('company_name', $legalName);

            if (! empty($tradeName)) {
                $duplicateQuery->orWhere('trade_name', $tradeName);
            }

            if (! empty($validated['gst_number'])) {
                $duplicateQuery->orWhere('gst_number', $validated['gst_number']);
            }

            if ($duplicateQuery->exists()) {
                return back()
                    ->withInput()
                    ->with('error', "A customer with this Legal Name, Trade Name, or GSTIN already exists. To proceed anyway, please check 'Confirm Duplicate' and submit again.")
                    ->with('requires_duplicate_confirmation', true);
            }
        }

        $tenantId = TenantManager::getTenantId();

        if (! empty($validated['customer_code'])) {
            $code = trim($validated['customer_code']);
            if (Customer::where('customer_code', $code)->exists()) {
                return back()
                    ->withInput()
                    ->withErrors(['customer_code' => "Customer Code '{$code}' is already in use in your account. Please use a unique code or leave blank to auto-generate."]);
            }
        } else {
            $code = Customer::generateNextCode($tenantId);
        }

        DB::beginTransaction();
        try {
            $creditLimit = isset($validated['credit_limit']) && is_numeric($validated['credit_limit']) ? (float) $validated['credit_limit'] : 0.00;
            $creditAction = ! empty($validated['credit_limit_action']) ? $validated['credit_limit_action'] : 'WARN';
            $paymentTerms = isset($validated['payment_terms_days']) && is_numeric($validated['payment_terms_days']) ? (int) $validated['payment_terms_days'] : 30;

            $customer = Customer::create([
                'customer_code' => $code,
                'company_name' => $legalName,
                'trade_name' => $tradeName,
                'gst_number' => $validated['gst_number'] ?? null,
                'pan_number' => $validated['pan_number'] ?? null,
                'primary_contact_person' => $validated['primary_contact_person'] ?? null,
                'primary_phone' => $validated['primary_phone'] ?? null,
                'primary_email' => $validated['primary_email'] ?? null,
                'address_line' => $validated['address_line'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? 'Gujarat',
                'pincode' => $validated['pincode'] ?? null,
                'credit_limit' => $creditLimit,
                'credit_limit_action' => $creditAction,
                'payment_terms_days' => $paymentTerms,
                'notes' => $validated['notes'] ?? null,
                'stage' => 'ACTIVE',
            ]);

            if (! empty($validated['primary_contact_person'])) {
                Contact::create([
                    'customer_id' => $customer->id,
                    'name' => $validated['primary_contact_person'],
                    'phone' => $validated['primary_phone'] ?? null,
                    'email' => $validated['primary_email'] ?? null,
                    'is_primary' => true,
                ]);
            }

            DB::commit();

            return redirect()->route('customers.show', $customer->id)->with('success', "✓ Customer '{$customer->company_name}' created successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create customer: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['password']),
            ]);

            return back()->withInput()->with('error', 'Failed to create customer: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'primary_contact_person' => 'nullable|string|max:255',
            'primary_phone' => 'nullable|string|max:30',
            'primary_email' => 'nullable|email|max:255',
            'address_line' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'payment_terms_days' => 'nullable|integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_limit_action' => 'nullable|string|in:WARN,BLOCK',
            'stage' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        if (! empty($validated['legal_name'])) {
            $validated['company_name'] = $validated['legal_name'];
            unset($validated['legal_name']);
        }

        if (array_key_exists('payment_terms_days', $validated) && $validated['payment_terms_days'] !== null && is_numeric($validated['payment_terms_days'])) {
            $validated['payment_terms_days'] = (int) $validated['payment_terms_days'];
        }

        if (array_key_exists('credit_limit', $validated) && $validated['credit_limit'] !== null && is_numeric($validated['credit_limit'])) {
            $validated['credit_limit'] = (float) $validated['credit_limit'];
        }

        DB::beginTransaction();
        try {
            $customer->update($validated);
            DB::commit();

            return back()->with('success', "✓ Customer '{$customer->company_name}' updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update customer #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update customer: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $name = $customer->company_name;

        // Check if has orders or invoices
        if ($customer->salesOrders()->count() > 0 || $customer->invoices()->count() > 0) {
            return back()->with('error', "Cannot delete customer '{$name}' because they have associated sales orders or invoices.");
        }

        DB::beginTransaction();
        try {
            $customer->contacts()->delete();
            $customer->followups()->delete();
            $customer->activities()->delete();
            $customer->delete();
            DB::commit();

            return redirect()->route('customers.index')->with('success', "✓ Customer '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete customer #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete customer: '.$e->getMessage());
        }
    }
}
