<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\CompanyFirmContext;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::with(['customer', 'lead', 'items.product'])
            ->orderByDesc('quotation_date')
            ->get();

        $products = Product::where('is_active', true)->get();
        $customers = Customer::all();
        $leads = Lead::all();

        return view('quotations.index', compact('quotations', 'products', 'customers', 'leads'));
    }

    public function show($id)
    {
        $quotation = Quotation::with(['customer', 'lead', 'items.product'])->findOrFail($id);

        return view('quotations.show', compact('quotation'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_company' => 'required|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:30',
            'recipient_email' => 'nullable|email|max:255',
            'payment_terms' => 'nullable|string',
            'freight_terms' => 'nullable|string',
            'delivery_timeline' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.rate' => 'required|numeric|min:1',
            'items.*.packaging' => 'nullable|string|max:100',
        ]);

        $subtotal = 0;
        $taxAmount = 0;
        foreach ($validated['items'] as $it) {
            $lineSubtotal = ((float) $it['quantity'] * (float) $it['rate']);
            $subtotal += $lineSubtotal;
            $prod = Product::find($it['product_id']);
            $ratePercent = $prod ? (float) ($prod->tax_rate ?? $prod->tax_rate_percent ?? 5.0) : 5.0;
            $taxAmount += round($lineSubtotal * ($ratePercent / 100), 2);
        }
        $taxAmount = round($taxAmount, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $tenant = TenantManager::getTenant();
        $prefix = $tenant ? $tenant->quotation_prefix : 'QUO';
        $quoteNumber = $prefix.'-'.date('Y').'-'.rand(100, 999);

        DB::beginTransaction();
        try {
            $quotation = Quotation::create([
                'firm_id' => CompanyFirmContext::getCurrentFirmId(),
                'quotation_number' => $quoteNumber,
                'quotation_date' => Carbon::today(),
                'valid_until' => Carbon::today()->addDays(14),
                'recipient_company' => $validated['recipient_company'],
                'recipient_name' => $validated['recipient_name'] ?? null,
                'recipient_phone' => $validated['recipient_phone'] ?? null,
                'recipient_email' => $validated['recipient_email'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'SENT',
                'payment_terms' => $validated['payment_terms'] ?? '100% advance or Letter of Credit at sight',
                'freight_terms' => $validated['freight_terms'] ?? 'Ex-Factory Mahuva / FOB Mundra Port',
                'delivery_timeline' => $validated['delivery_timeline'] ?? '7 to 10 days from PO confirmation',
            ]);

            foreach ($validated['items'] as $it) {
                $amt = $it['quantity'] * $it['rate'];
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'uom' => 'KG',
                    'rate' => $it['rate'],
                    'amount' => $amt,
                    'packaging' => $it['packaging'] ?? '25 KG HDPE Bag with Poly Liner',
                ]);
            }

            DB::commit();

            return redirect()->route('quotations.show', $quotation->id)->with('success', "✓ Quotation {$quotation->quotation_number} generated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to generate quotation: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to generate quotation: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);

        $validated = $request->validate([
            'recipient_company' => 'required|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:30',
            'recipient_email' => 'nullable|email|max:255',
            'payment_terms' => 'nullable|string',
            'freight_terms' => 'nullable|string',
            'delivery_timeline' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $quotation->update($validated);
            DB::commit();

            return back()->with('success', "✓ Quotation {$quotation->quotation_number} updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update quotation #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update quotation: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $quotation = Quotation::findOrFail($id);
        $num = $quotation->quotation_number;

        DB::beginTransaction();
        try {
            $quotation->items()->delete();
            $quotation->delete();
            DB::commit();

            return redirect()->route('quotations.index')->with('success', "✓ Quotation {$num} deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete quotation #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete quotation: '.$e->getMessage());
        }
    }

    public function convert(Request $request, $id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        if (! $quotation->customer_id && ! $request->has('customer_id')) {
            return back()->with('error', 'Cannot convert to Sales Order: Quotation must be linked to a Customer Account first.');
        }

        $customerId = $request->input('customer_id', $quotation->customer_id);

        $tenantId = TenantManager::getTenantId();
        $orderNo = 'SO-'.date('Ymd').'-'.rand(100, 999);

        DB::beginTransaction();
        try {
            $order = SalesOrder::create([
                'firm_id' => CompanyFirmContext::getCurrentFirmId() ?? $quotation->firm_id,
                'order_number' => $orderNo,
                'po_number' => 'PO-'.$quotation->quotation_number,
                'po_date' => Carbon::today(),
                'customer_id' => $customerId,
                'order_date' => Carbon::today(),
                'subtotal' => $quotation->subtotal,
                'tax_amount' => $quotation->tax_amount,
                'total_amount' => $quotation->total_amount,
                'balance_amount' => $quotation->total_amount,
                'payment_terms' => $quotation->payment_terms ?: '30 Days Credit',
                'status' => 'CONFIRMED',
                'notes' => "Converted from Quotation #{$quotation->quotation_number}.",
            ]);

            foreach ($quotation->items as $it) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $it->product_id,
                    'order_qty' => $it->quantity,
                    'rate' => $it->rate,
                    'order_value' => $it->amount,
                    'shipped_qty' => 0,
                    'balance_qty' => $it->quantity,
                    'status' => 'PENDING',
                ]);
            }

            $quotation->update(['status' => 'ACCEPTED']);

            DB::commit();

            return redirect()->route('orders.index')->with('success', "✓ Quotation {$quotation->quotation_number} successfully converted to Sales Order {$orderNo}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to convert quotation #{$id} to order: ".$e->getMessage(), [
                'exception' => $e,
                'customer_id' => $customerId,
            ]);

            return back()->with('error', 'Failed to convert quotation to order: '.$e->getMessage());
        }
    }
}
