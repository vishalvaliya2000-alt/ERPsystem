<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;

beforeEach(function () {
    $this->tenant = Tenant::firstOrCreate(
        ['slug' => 'test-advance-tenant'],
        ['name' => 'Advance Test Tenant']
    );

    $this->user = User::firstOrCreate(
        ['email' => 'test_advance_user@example.com'],
        [
            'name' => 'Advance Test Manager',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]
    );

    TenantManager::setTenant($this->tenant);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Advance Test Spices Pvt Ltd',
        'customer_code' => 'CUST-TEST-'.rand(1000, 9999),
        'city' => 'Mahuva',
        'state' => 'Gujarat',
        'payment_terms_days' => 30,
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'product_code' => 'PRD-ADV-'.rand(100, 999),
        'product_name' => 'Dehydrated White Onion Kibbled',
        'standard_rate' => 150,
    ]);
});

test('creating a sales order with advance_required sets status to ADVANCE_PENDING and initializes balance', function () {
    $response = $this->actingAs($this->user)->post(route('orders.store'), [
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-TEST-'.rand(1000, 9999),
        'po_number' => 'BUYER-PO-991',
        'order_date' => now()->format('Y-m-d'),
        'payment_terms' => '30% Advance, Balance on Dispatch',
        'advance_required' => 50000,
        'items' => [
            [
                'product_id' => $this->product->id,
                'order_qty' => 1000,
                'rate' => 150,
            ],
        ],
    ]);

    $response->assertRedirect(route('orders.index'));

    $order = SalesOrder::where('po_number', 'BUYER-PO-991')->first();
    expect($order)->not->toBeNull();
    expect((float) $order->advance_required)->toBe(50000.0);
    expect((float) $order->advance_received)->toBe(0.0);
    expect($order->status)->toBe('ADVANCE_PENDING');
    expect((float) $order->balance_amount)->toBe((float) $order->total_amount);
});

test('recording an advance payment against a PO updates advance_received and flips status to CONFIRMED when fulfilled', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-REC-'.rand(1000, 9999),
        'po_number' => 'BUYER-REC-1',
        'order_date' => now(),
        'subtotal' => 100000,
        'tax_amount' => 5000,
        'total_amount' => 105000,
        'advance_required' => 30000,
        'advance_received' => 0,
        'balance_amount' => 105000,
        'status' => 'ADVANCE_PENDING',
    ]);

    // 1. Record partial advance of 15,000 (status should remain ADVANCE_PENDING)
    $res1 = $this->actingAs($this->user)->post(route('orders.advance', $order->id), [
        'amount_received' => 15000,
        'payment_mode' => 'RTGS',
        'reference_number' => 'UTR-ADV-PART1',
        'receipt_date' => now()->format('Y-m-d'),
        'remarks' => 'First partial tranche of advance',
    ]);

    $res1->assertRedirect();
    $order->refresh();

    expect((float) $order->advance_received)->toBe(15000.0);
    expect((float) $order->balance_amount)->toBe(90000.0);
    expect($order->status)->toBe('ADVANCE_PENDING');

    // 2. Record remaining advance of 15,000 (status should transition to CONFIRMED)
    $res2 = $this->actingAs($this->user)->post(route('orders.advance', $order->id), [
        'amount_received' => 15000,
        'payment_mode' => 'NEFT',
        'reference_number' => 'UTR-ADV-PART2',
        'receipt_date' => now()->format('Y-m-d'),
        'remarks' => 'Second tranche of advance',
    ]);

    $res2->assertRedirect();
    $order->refresh();

    expect((float) $order->advance_received)->toBe(30000.0);
    expect((float) $order->balance_amount)->toBe(75000.0);
    expect($order->status)->toBe('CONFIRMED');

    // Verify PaymentReceipt records
    $receipts = PaymentReceipt::where('sales_order_id', $order->id)->get();
    expect($receipts)->toHaveCount(2);
    expect($receipts->first()->receipt_type)->toBe('PO_ADVANCE');
    expect($receipts->first()->invoice_id)->toBeNull();
});

test('cannot delete a PO that has recorded advance payments', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-DEL-'.rand(1000, 9999),
        'po_number' => 'BUYER-DEL-1',
        'order_date' => now(),
        'subtotal' => 50000,
        'tax_amount' => 2500,
        'total_amount' => 52500,
        'advance_required' => 20000,
        'advance_received' => 20000,
        'balance_amount' => 32500,
        'status' => 'CONFIRMED',
    ]);

    PaymentReceipt::create([
        'tenant_id' => $this->tenant->id,
        'receipt_number' => 'ADV-DEL-TEST',
        'sales_order_id' => $order->id,
        'customer_id' => $this->customer->id,
        'receipt_type' => 'PO_ADVANCE',
        'amount_received' => 20000,
        'payment_mode' => 'RTGS',
        'receipt_date' => now(),
    ]);

    $response = $this->actingAs($this->user)->post(route('orders.destroy', $order->id));
    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Verify order still exists
    expect(SalesOrder::find($order->id))->not->toBeNull();
});

test('generating a tax invoice against a PO automatically applies the advance and reduces balance_due', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-INV-'.rand(1000, 9999),
        'po_number' => 'BUYER-INV-1',
        'order_date' => now(),
        'subtotal' => 100000,
        'tax_amount' => 5000,
        'total_amount' => 105000,
        'advance_required' => 35000,
        'advance_received' => 35000,
        'balance_amount' => 70000,
        'status' => 'CONFIRMED',
    ]);

    // Record the advance receipt
    PaymentReceipt::create([
        'tenant_id' => $this->tenant->id,
        'receipt_number' => 'ADV-TEST-INV',
        'sales_order_id' => $order->id,
        'customer_id' => $this->customer->id,
        'receipt_type' => 'PO_ADVANCE',
        'amount_received' => 35000,
        'payment_mode' => 'RTGS',
        'receipt_date' => now(),
    ]);

    expect($order->unadjustedAdvanceAmount())->toBe(35000.0);

    // Create Tax Invoice for 100,000 subtotal + 5% GST = 105,000
    $invNumber = 'INV-ADV-'.rand(1000, 9999);
    $response = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => $invNumber,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'material_subtotal' => 100000,
        'freight_amount' => 0,
        'gst_rate' => 5,
    ]);

    $response->assertRedirect(route('invoices.index'));

    $invoice = Invoice::where('invoice_number', $invNumber)->first();
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total_amount)->toBe(105000.0);
    expect((float) $invoice->amount_received)->toBe(35000.0);
    expect((float) $invoice->balance_due)->toBe(70000.0);
    expect($invoice->status)->toBe('PART_PAID');

    // Verify unadjusted advance on PO is now zero
    $order->refresh();
    expect($order->unadjustedAdvanceAmount())->toBe(0.0);

    // Verify ADVANCE_OFFSET receipt was created
    $offsetReceipt = PaymentReceipt::where('invoice_id', $invoice->id)
        ->where('payment_mode', 'ADVANCE_OFFSET')
        ->first();
    expect($offsetReceipt)->not->toBeNull();
    expect((float) $offsetReceipt->amount_received)->toBe(35000.0);
});

test('tax invoice PDF renders advance deduction row when amount_received is present', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-PDF-'.rand(1000, 9999),
        'po_number' => 'BUYER-PDF-1',
        'order_date' => now(),
        'subtotal' => 50000,
        'tax_amount' => 2500,
        'total_amount' => 52500,
        'advance_required' => 20000,
        'advance_received' => 20000,
        'balance_amount' => 32500,
        'status' => 'CONFIRMED',
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => 'INV-PDF-'.rand(1000, 9999),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'material_subtotal' => 50000,
        'freight_amount' => 0,
        'subtotal' => 50000,
        'gst_amount' => 2500,
        'total_amount' => 52500,
        'amount_received' => 20000,
        'balance_due' => 32500,
        'status' => 'PART_PAID',
    ]);

    $response = $this->actingAs($this->user)->get(route('invoices.pdf', $invoice->id));
    $response->assertStatus(200);

    // Rendered PDF HTML view check
    $view = view('invoices.pdf', compact('invoice'))->render();
    expect($view)->toContain('Less Advance Received / Paid');
    expect($view)->toContain('NET BALANCE PAYABLE');
});

test('can manually adjust advance deduction on partial shipment invoice and reserve remainder for next invoice', function () {
    // Scenario: Order for 1,000 KG @ 150 = 150,000 + 5% GST = 157,500 with 30% advance (45,000)
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-PART-'.rand(1000, 9999),
        'po_number' => 'BUYER-PART-1',
        'order_date' => now(),
        'subtotal' => 150000,
        'tax_amount' => 7500,
        'total_amount' => 157500,
        'advance_required' => 45000,
        'advance_received' => 45000,
        'balance_amount' => 112500,
        'status' => 'CONFIRMED',
    ]);

    PaymentReceipt::create([
        'tenant_id' => $this->tenant->id,
        'receipt_number' => 'ADV-PART-1',
        'sales_order_id' => $order->id,
        'customer_id' => $this->customer->id,
        'receipt_type' => 'PO_ADVANCE',
        'amount_received' => 45000,
        'payment_mode' => 'RTGS',
        'receipt_date' => now(),
    ]);

    expect($order->unadjustedAdvanceAmount())->toBe(45000.0);

    // 1. Invoice First Partial Delivery: 500 KG = 75,000 + 5% GST = 78,750
    // User manually specifies advance_deduction_amount = 22,500 (50% of the advance)
    $inv1Number = 'INV-PART-1-'.rand(1000, 9999);
    $res1 = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => $inv1Number,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'material_subtotal' => 75000,
        'freight_amount' => 0,
        'gst_rate' => 5,
        'advance_deduction_amount' => 22500,
    ]);

    $res1->assertRedirect(route('invoices.index'));

    $inv1 = Invoice::where('invoice_number', $inv1Number)->first();
    expect($inv1)->not->toBeNull();
    expect((float) $inv1->total_amount)->toBe(78750.0);
    expect((float) $inv1->amount_received)->toBe(22500.0);
    expect((float) $inv1->balance_due)->toBe(56250.0); // 78,750 - 22,500
    expect($inv1->status)->toBe('PART_PAID');

    // Verify exactly 22,500 remaining unadjusted advance on PO for the next invoice
    $order->refresh();
    expect($order->unadjustedAdvanceAmount())->toBe(22500.0);

    // 2. Invoice Second Partial Delivery: Remaining 500 KG = 75,000 + 5% GST = 78,750
    // User deducts the remaining 22,500 advance
    $inv2Number = 'INV-PART-2-'.rand(1000, 9999);
    $res2 = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => $inv2Number,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'material_subtotal' => 75000,
        'freight_amount' => 0,
        'gst_rate' => 5,
        'advance_deduction_amount' => 22500,
    ]);

    $res2->assertRedirect(route('invoices.index'));

    $inv2 = Invoice::where('invoice_number', $inv2Number)->first();
    expect($inv2)->not->toBeNull();
    expect((float) $inv2->total_amount)->toBe(78750.0);
    expect((float) $inv2->amount_received)->toBe(22500.0);
    expect((float) $inv2->balance_due)->toBe(56250.0);

    // Now all advance is fully utilized
    $order->refresh();
    expect($order->unadjustedAdvanceAmount())->toBe(0.0);
});

test('user can choose zero advance deduction to preserve all advance for later', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PO-ZERO-'.rand(1000, 9999),
        'po_number' => 'BUYER-ZERO-1',
        'order_date' => now(),
        'subtotal' => 100000,
        'tax_amount' => 5000,
        'total_amount' => 105000,
        'advance_required' => 30000,
        'advance_received' => 30000,
        'balance_amount' => 75000,
        'status' => 'CONFIRMED',
    ]);

    PaymentReceipt::create([
        'tenant_id' => $this->tenant->id,
        'receipt_number' => 'ADV-ZERO-1',
        'sales_order_id' => $order->id,
        'customer_id' => $this->customer->id,
        'receipt_type' => 'PO_ADVANCE',
        'amount_received' => 30000,
        'payment_mode' => 'RTGS',
        'receipt_date' => now(),
    ]);

    $invNumber = 'INV-ZERO-'.rand(1000, 9999);
    $res = $this->actingAs($this->user)->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => $invNumber,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'material_subtotal' => 50000,
        'freight_amount' => 0,
        'gst_rate' => 5,
        'advance_deduction_amount' => 0, // Explicit zero deduction
    ]);

    $res->assertRedirect(route('invoices.index'));

    $inv = Invoice::where('invoice_number', $invNumber)->first();
    expect($inv)->not->toBeNull();
    expect((float) $inv->total_amount)->toBe(52500.0);
    expect((float) $inv->amount_received)->toBe(0.0);
    expect((float) $inv->balance_due)->toBe(52500.0);
    expect($inv->status)->toBe('ISSUED');

    // 100% of advance remains unadjusted on PO
    $order->refresh();
    expect($order->unadjustedAdvanceAmount())->toBe(30000.0);
});
