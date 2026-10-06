<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Carbon\Carbon;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Test Payment Terms Tenant',
        'slug' => 'acme-test-payment-terms-tenant',
        'industry' => 'Food Processing & Exports',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    if (! $this->user->tenant_id) {
        $this->user->tenant_id = $this->tenant->id;
        $this->user->save();
    }

    TenantManager::setTenant($this->tenant);

    $this->customer15Days = Customer::create([
        'tenant_id' => $this->tenant->id,
        'customer_code' => 'CUST-15D-'.rand(100, 999),
        'company_name' => 'Test 15 Days Credit Customer',
        'city' => 'Surat',
        'state' => 'Gujarat',
        'gst_number' => '24AAACT'.rand(1000, 9999).'Z1Z1',
        'payment_terms_days' => 15,
        'stage' => 'ACTIVE',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'product_code' => 'PRD-TEST-'.rand(100, 999),
        'product_name' => 'Garlic Powder Fine Mesh',
        'granulation' => 'Fine Powder',
        'standard_rate' => 160,
        'hsn_code' => '07129020',
        'is_active' => true,
    ]);
});

test('invoices.index view receives customersMap with payment_terms_days and customer orders with payment_terms', function () {
    $response = $this->actingAs($this->user)->get(route('invoices.index'));

    $response->assertStatus(200);
    $response->assertViewHas('customersMap');
    $response->assertViewHas('shipmentOptions');
    $response->assertViewHas('customerOrdersData');

    $map = $response->viewData('customersMap');
    expect($map)->toBeArray();
    expect($map[$this->customer15Days->id]['payment_terms_days'])->toBe(15);
});

test('storing invoice uses customer payment terms days when due_date is empty', function () {
    $invDate = Carbon::today()->format('Y-m-d');
    $expectedDueDate = Carbon::today()->addDays(15)->format('Y-m-d');

    $response = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer15Days->id,
        'invoice_number' => 'INV-TEST-TERM-'.rand(1000, 9999),
        'invoice_date' => $invDate,
        'due_date' => '', // omitted to test fallback
        'material_subtotal' => 50000,
        'gst_rate' => 5,
    ]);

    $response->assertRedirect(route('invoices.index'));

    $invoice = Invoice::where('customer_id', $this->customer15Days->id)->latest('id')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->due_date->format('Y-m-d'))->toBe($expectedDueDate);
});

test('storing invoice honors explicit auto-calculated due_date from reactive modal', function () {
    $invDate = '2026-10-15';
    $customDueDate = '2026-11-15';

    $response = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer15Days->id,
        'invoice_number' => 'INV-TEST-CUSTOM-'.rand(1000, 9999),
        'invoice_date' => $invDate,
        'due_date' => $customDueDate,
        'material_subtotal' => 60000,
        'gst_rate' => 5,
    ]);

    $response->assertRedirect(route('invoices.index'));

    $invoice = Invoice::where('customer_id', $this->customer15Days->id)->latest('id')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->due_date->format('Y-m-d'))->toBe('2026-11-15');
});

test('storing invoice 100% settled by advance aligns due date with invoice date when empty', function () {
    // Create sales order with advance
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'PO-ADV-'.rand(1000, 9999),
        'customer_id' => $this->customer15Days->id,
        'order_date' => Carbon::today(),
        'subtotal' => 10000,
        'tax_amount' => 500,
        'total_amount' => 10500,
        'advance_required' => 10500,
        'advance_received' => 10500,
        'balance_amount' => 0,
        'payment_terms' => '100% Advance',
        'status' => 'CONFIRMED',
    ]);

    $invDate = Carbon::today()->format('Y-m-d');

    $response = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer15Days->id,
        'sales_order_id' => $order->id,
        'invoice_number' => 'INV-TEST-FULLADV-'.rand(1000, 9999),
        'invoice_date' => $invDate,
        'due_date' => '', // omitted to test 100% advance alignment
        'material_subtotal' => 10000,
        'gst_rate' => 5,
        'advance_deduction_amount' => 10500,
    ]);

    $response->assertRedirect(route('invoices.index'));

    $invoice = Invoice::where('customer_id', $this->customer15Days->id)->latest('id')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->balance_due)->toEqual(0);
    expect($invoice->status)->toBe('PAID');
    expect($invoice->due_date->format('Y-m-d'))->toBe($invDate);
});
