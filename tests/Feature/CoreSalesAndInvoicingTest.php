<?php

use App\Actions\ProvisionTenantAction;
use App\Models\Customer;
use App\Models\Firm;
use App\Models\Invoice;
use App\Models\PartyPriceOverride;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceSlab;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\CompanyFirmContext;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['salesinvoicingcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['salesinvoicingcorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_salesinvoicingcorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Gujarat Agro Exports Ltd',
        'subdomain' => 'salesinvoicingcorp',
        'owner_name' => 'Rajesh Mehta',
        'owner_email' => 'rajesh@gujaratagro.com',
        'password' => 'OwnerPassword123!',
        'plan' => 'Standard',
        'gstin' => '24AABCG1234F1Z9',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        $testDbs = DB::select("SHOW DATABASES LIKE 'saas_erp_test_tenant_%'");
        foreach ($testDbs as $dbObj) {
            $prop = array_keys((array) $dbObj)[0];
            $dbName = $dbObj->$prop;
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        }
        DB::table('domains')->whereIn('tenant_id', ['salesinvoicingcorp'])->delete();
        DB::table('tenants')->whereIn('id', ['salesinvoicingcorp'])->delete();
    } catch (Throwable $e) {
        // Ignore
    }
});

test('pricing service resolves rates with hierarchy: party override > volume slab > standard rate', function () {
    $this->tenant->run(function () {
        $category = ProductCategory::create([
            'name' => 'Dehydrated Onion',
            'slug' => 'dehydrated-onion',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Dehydrated White Onion Flakes A-Grade',
            'product_code' => 'DWOF-AG-01',
            'standard_rate' => 250.00,
            'standard_cost' => 180.00,
            'hsn_code' => '07129090',
            'uom' => 'KG',
            'min_order_qty' => 50.0,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_name' => 'Maharaja Spices Pvt Ltd',
            'customer_code' => 'CUST-1001',
            'state' => 'Gujarat',
            'state_code' => '24',
        ]);

        // 1. Fallback to standard base rate
        $rate1 = PricingService::resolveRate($customer, $product, 10.0);
        expect($rate1['source'])->toBe('STANDARD_RATE');
        expect($rate1['rate'])->toBe(250.00);
        expect($rate1['cost'])->toBe(180.00);
        expect($rate1['unit_margin'])->toBe(70.00);
        expect($rate1['margin_percentage'])->toBe(28.00);

        // 2. Add volume slabs
        ProductPriceSlab::create([
            'product_id' => $product->id,
            'tier_name' => 'Wholesale Tier 1',
            'min_quantity' => 100.0,
            'rate' => 230.00,
        ]);

        ProductPriceSlab::create([
            'product_id' => $product->id,
            'tier_name' => 'Bulk Container Tier',
            'min_quantity' => 500.0,
            'rate' => 210.00,
        ]);

        // Below slab quantity still uses standard rate
        $rate2 = PricingService::resolveRate($customer, $product, 80.0);
        expect($rate2['source'])->toBe('STANDARD_RATE');
        expect($rate2['rate'])->toBe(250.00);

        // At or above 100 kg uses Wholesale Tier 1
        $rate3 = PricingService::resolveRate($customer, $product, 150.0);
        expect($rate3['source'])->toBe('PRICE_TIER');
        expect($rate3['rate'])->toBe(230.00);
        expect($rate3['unit_margin'])->toBe(50.00);

        // At or above 500 kg uses Bulk Container Tier
        $rate4 = PricingService::resolveRate($customer, $product, 600.0);
        expect($rate4['source'])->toBe('PRICE_TIER');
        expect($rate4['rate'])->toBe(210.00);
        expect($rate4['unit_margin'])->toBe(30.00);

        // 3. Add agreed Party-Specific Price Override
        PartyPriceOverride::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'custom_rate' => 200.00,
            'custom_hsn' => '07129090',
        ]);

        // Party price override takes precedence even for small or huge orders
        $rateOverrideSmall = PricingService::resolveRate($customer, $product, 10.0);
        expect($rateOverrideSmall['source'])->toBe('PARTY_OVERRIDE');
        expect($rateOverrideSmall['rate'])->toBe(200.00);
        expect($rateOverrideSmall['unit_margin'])->toBe(20.00);

        $rateOverrideLarge = PricingService::resolveRate($customer, $product, 1000.0);
        expect($rateOverrideLarge['source'])->toBe('PARTY_OVERRIDE');
        expect($rateOverrideLarge['rate'])->toBe(200.00);
    });
});

test('customer credit limit enforcement respects NONE, WARN, and BLOCK policies', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://salesinvoicingcorp.localhost';

    $customerId = null;
    $productId = null;

    $this->tenant->run(function () use (&$customerId, &$productId) {
        $product = Product::create([
            'product_name' => 'Toasted Garlic Powder',
            'product_code' => 'TGP-01',
            'standard_rate' => 300.00,
            'standard_cost' => 200.00,
            'uom' => 'KG',
            'is_active' => true,
        ]);
        $productId = $product->id;

        $customer = Customer::create([
            'company_name' => 'Sterling Foods LLP',
            'customer_code' => 'CUST-2002',
            'credit_limit' => 50000.00,
            'credit_limit_action' => 'BLOCK',
            'outstanding_amount' => 10000.00,
            'state' => 'Gujarat',
            'state_code' => '24',
        ]);
        $customerId = $customer->id;
    });

    // 1. Order within limit (100 kg @ ₹300 = ₹30,000 + ₹10,000 outstanding = ₹40,000 <= ₹50,000)
    $validOrderRes = $this->actingAs($owner)
        ->post("{$baseUrl}/orders", [
            'customer_id' => $customerId,
            'po_number' => 'PO-STERLING-001',
            'order_date' => Carbon::today()->toDateString(),
            'payment_terms' => '30 Days Net',
            'items' => [
                [
                    'product_id' => $productId,
                    'order_qty' => 100,
                    'rate' => 300.00,
                ],
            ],
        ]);

    $validOrderRes->assertStatus(302);
    $validOrderRes->assertSessionHas('success');

    // 2. Order exceeding limit in BLOCK mode (200 kg @ ₹300 = ₹60,000 + ₹10,000 = ₹70,000 > ₹50,000 limit)
    $blockedOrderRes = $this->actingAs($owner)
        ->from("{$baseUrl}/orders")
        ->post("{$baseUrl}/orders", [
            'customer_id' => $customerId,
            'po_number' => 'PO-STERLING-002',
            'order_date' => Carbon::today()->toDateString(),
            'payment_terms' => '30 Days Net',
            'items' => [
                [
                    'product_id' => $productId,
                    'order_qty' => 200,
                    'rate' => 300.00,
                ],
            ],
        ]);

    $blockedOrderRes->assertStatus(302);
    $blockedOrderRes->assertSessionHas('error');

    $this->tenant->run(function () {
        expect(SalesOrder::where('po_number', 'PO-STERLING-002')->first())->toBeNull();
    });

    // 3. Switch credit limit action to WARN
    $this->tenant->run(function () use ($customerId) {
        Customer::where('id', $customerId)->update(['credit_limit_action' => 'WARN']);
    });

    $warnOrderRes = $this->actingAs($owner)
        ->post("{$baseUrl}/orders", [
            'customer_id' => $customerId,
            'po_number' => 'PO-STERLING-003',
            'order_date' => Carbon::today()->toDateString(),
            'payment_terms' => '30 Days Net',
            'items' => [
                [
                    'product_id' => $productId,
                    'order_qty' => 200,
                    'rate' => 300.00,
                ],
            ],
        ]);

    $warnOrderRes->assertStatus(302);
    $warnOrderRes->assertSessionHas('success');
    $warnOrderRes->assertSessionHas('warning');

    $this->tenant->run(function () {
        expect(SalesOrder::where('po_number', 'PO-STERLING-003')->first())->not->toBeNull();
    });
});

test('invoice calculates line costs, profit margins, and gates profit visibility by permission', function () {
    $owner = null;
    $dispatchStaff = null;

    $this->tenant->run(function () use (&$owner, &$dispatchStaff) {
        $owner = User::where('role', 'owner')->first();

        // Create a shipping/dispatch staff member without invoices.view_profit
        $dispatchStaff = User::create([
            'name' => 'Sanjay Dispatcher',
            'email' => 'sanjay@gujaratagro.com',
            'password' => bcrypt('DispatchPass123!'),
            'role' => 'staff',
            'is_active' => true,
        ]);
        $dispatchStaff->assignRole('staff');

        // Grant invoices.view permission but NOT invoices.view_profit
        $viewPerm = Permission::firstOrCreate(['name' => 'invoices.view', 'guard_name' => 'web']);
        $dispatchStaff->givePermissionTo($viewPerm);
    });

    $baseUrl = 'http://salesinvoicingcorp.localhost';

    $invoiceId = null;

    $this->tenant->run(function () use (&$invoiceId) {
        $firm = CompanyFirmContext::getCurrentFirm();

        $product = Product::create([
            'product_name' => 'Kibbled White Onion',
            'product_code' => 'KWO-01',
            'standard_rate' => 280.00,
            'standard_cost' => 190.00,
            'hsn_code' => '07129090',
            'uom' => 'KG',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_name' => 'Apex Condiments Corp',
            'customer_code' => 'CUST-3003',
            'state' => 'Gujarat',
            'state_code' => '24',
        ]);

        $salesOrder = SalesOrder::create([
            'firm_id' => $firm->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-APEX-01',
            'po_number' => 'PO-APEX-01',
            'order_date' => Carbon::today(),
            'status' => 'PENDING',
            'total_amount' => 56000.00,
        ]);

        $soItem = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'order_qty' => 200,
            'shipped_qty' => 200,
            'balance_qty' => 0,
            'rate' => 280.00,
            'order_value' => 56000.00,
            'unit_cost' => 190.00,
        ]);

        // Generate invoice with 200 KG @ ₹280 = ₹56,000 material, cost = 200 * ₹190 = ₹38,000
        // Gross Profit = ₹18,000 (32.14%)
        $taxable = 56000.00;
        $gstAmount = round($taxable * 0.05, 2); // 2,800
        $cgst = round($gstAmount / 2, 2); // 1,400
        $sgst = round($gstAmount / 2, 2); // 1,400

        $invoice = Invoice::create([
            'firm_id' => $firm->id,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'invoice_number' => 'INV-2026-0001',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'material_subtotal' => 56000.00,
            'taxable_amount' => 56000.00,
            'subtotal' => 56000.00,
            'gst_amount' => $gstAmount,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => 0.0,
            'total_amount' => 56000.00 + $gstAmount,
            'balance_due' => 56000.00 + $gstAmount,
            'total_cost_amount' => 38000.00,
            'gross_profit_amount' => 18000.00,
            'gross_profit_percentage' => 32.14,
            'status' => 'ISSUED',
        ]);

        $invoice->items()->create([
            'sales_order_id' => $salesOrder->id,
            'sales_order_item_id' => $soItem->id,
            'product_id' => $product->id,
            'description' => $product->product_name,
            'hsn_code' => $product->hsn_code,
            'quantity' => 200,
            'rate' => 280.00,
            'amount' => 56000.00,
            'unit_cost' => 190.00,
            'line_cost' => 38000.00,
            'line_profit' => 18000.00,
        ]);

        $invoiceId = $invoice->id;
    });

    // 1. Verify owner can see profit metrics on invoice index
    $ownerViewRes = $this->actingAs($owner)->get("{$baseUrl}/invoices");
    $ownerViewRes->assertStatus(200);
    $ownerViewRes->assertSee('Margin / Profit');
    $ownerViewRes->assertSee('32.14%');

    // 2. Verify staff without invoices.view_profit cannot see profit badges
    $staffViewRes = $this->actingAs($dispatchStaff)->get("{$baseUrl}/invoices");
    $staffViewRes->assertStatus(200);
    $staffViewRes->assertDontSee('Margin / Profit');
    $staffViewRes->assertDontSee('32.14%');
});

test('combine multiple sales orders into one tax invoice with item traceability and advance deduction', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://salesinvoicingcorp.localhost';

    $customerId = null;
    $order1Id = null;
    $order2Id = null;

    $this->tenant->run(function () use (&$customerId, &$order1Id, &$order2Id) {
        $firm = CompanyFirmContext::getCurrentFirm();

        $onion = Product::create([
            'product_name' => 'Minced White Onion',
            'product_code' => 'MWO-01',
            'standard_rate' => 240.00,
            'standard_cost' => 170.00,
            'hsn_code' => '07129090',
            'uom' => 'KG',
            'is_active' => true,
        ]);

        $garlic = Product::create([
            'product_name' => 'Pure Garlic Powder',
            'product_code' => 'PGP-01',
            'standard_rate' => 320.00,
            'standard_cost' => 220.00,
            'hsn_code' => '07129090',
            'uom' => 'KG',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_name' => 'National Food Blenders Ltd',
            'customer_code' => 'CUST-4004',
            'state' => 'Maharashtra',
            'state_code' => '27', // Inter-state compared to Firm Gujarat (24)
            'payment_terms_days' => 45,
        ]);
        $customerId = $customer->id;

        // Sales Order 1: 100 KG Onion = ₹24,000, with ₹10,000 advance received
        $so1 = SalesOrder::create([
            'firm_id' => $firm->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-NFB-001',
            'po_number' => 'PO-NFB-A',
            'order_date' => Carbon::today()->subDays(5),
            'status' => 'PENDING',
            'total_amount' => 24000.00,
            'advance_received' => 10000.00,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so1->id,
            'product_id' => $onion->id,
            'order_qty' => 100,
            'balance_qty' => 100,
            'rate' => 240.00,
            'order_value' => 24000.00,
            'unit_cost' => 170.00,
        ]);
        $order1Id = $so1->id;

        // Sales Order 2: 50 KG Garlic = ₹16,000, no advance
        $so2 = SalesOrder::create([
            'firm_id' => $firm->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-NFB-002',
            'po_number' => 'PO-NFB-B',
            'order_date' => Carbon::today()->subDays(2),
            'status' => 'PENDING',
            'total_amount' => 16000.00,
            'advance_received' => 0.0,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so2->id,
            'product_id' => $garlic->id,
            'order_qty' => 50,
            'balance_qty' => 50,
            'rate' => 320.00,
            'order_value' => 16000.00,
            'unit_cost' => 220.00,
        ]);
        $order2Id = $so2->id;
    });

    // Execute combine orders into one Tax Invoice
    $combineRes = $this->actingAs($owner)
        ->post("{$baseUrl}/invoices/combine-orders", [
            'customer_id' => $customerId,
            'sales_order_ids' => [$order1Id, $order2Id],
            'invoice_number' => 'COMB-INV-001',
            'invoice_date' => Carbon::today()->toDateString(),
            'freight_amount' => 2000.00,
            'gst_rate' => 5.0,
            'tcs_rate' => 0.1,
            'tds_rate' => 0.0,
            'notes' => 'Consolidated shipment for October delivery schedule.',
        ]);

    $combineRes->assertStatus(302);
    $combineRes->assertSessionHas('success');

    $this->tenant->run(function () use ($order1Id, $order2Id) {
        $invoice = Invoice::where('invoice_number', 'COMB-INV-001')->with('items')->first();
        expect($invoice)->not->toBeNull();

        // 1. Material total = 24,000 + 16,000 = 40,000
        expect((float) $invoice->material_subtotal)->toBe(40000.00);
        expect((float) $invoice->freight_amount)->toBe(2000.00);
        expect((float) $invoice->taxable_amount)->toBe(42000.00);

        // 2. Inter-state: Gujarat (24) -> Maharashtra (27) -> IGST 5% of 42,000 = 2,100
        expect((float) $invoice->gst_amount)->toBe(2100.00);
        expect((float) $invoice->cgst_amount)->toBe(0.0);
        expect((float) $invoice->sgst_amount)->toBe(0.0);
        expect((float) $invoice->igst_amount)->toBe(2100.00);

        // 3. TCS 0.1% on 42,000 = 42.00
        expect((float) $invoice->tcs_amount)->toBe(42.00);

        // Total = 42,000 + 2,100 + 42 = 44,142
        expect((float) $invoice->total_amount)->toBe(44142.00);

        // Advance adjusted = 10,000; Balance due = 34,142
        expect((float) $invoice->amount_received)->toBe(10000.00);
        expect((float) $invoice->balance_due)->toBe(34142.00);
        expect($invoice->status)->toBe('PART_PAID');

        // 4. Line items traceability: 2 items referencing the respective sales orders
        expect($invoice->items)->toHaveCount(2);
        $orderIds = $invoice->items->pluck('sales_order_id')->all();
        expect($orderIds)->toContain($order1Id);
        expect($orderIds)->toContain($order2Id);

        // 5. Total cost = (100 * 170) + (50 * 220) = 17,000 + 11,000 = 28,000
        // Gross Profit = 40,000 - 28,000 = 12,000 (30.0%)
        expect((float) $invoice->total_cost_amount)->toBe(28000.00);
        expect((float) $invoice->gross_profit_amount)->toBe(12000.00);
        expect((float) $invoice->gross_profit_percentage)->toBe(30.00);

        // 6. Source Sales Orders updated to INVOICED
        $so1 = SalesOrder::find($order1Id);
        $so2 = SalesOrder::find($order2Id);
        expect($so1->status)->toBe('INVOICED');
        expect($so2->status)->toBe('INVOICED');
    });
});

test('recycle bin lifecycle handles soft deletes, restores, and permanent purging with rbac guards', function () {
    $owner = null;
    $staff = null;

    $this->tenant->run(function () use (&$owner, &$staff) {
        $owner = User::where('role', 'owner')->first();

        $staff = User::create([
            'name' => 'Kiran Staff',
            'email' => 'kiran@gujaratagro.com',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole('staff');
    });

    $baseUrl = 'http://salesinvoicingcorp.localhost';

    $customerId = null;
    $productId = null;

    $this->tenant->run(function () use (&$customerId, &$productId) {
        $customer = Customer::create([
            'company_name' => 'Heritage Foodworks Ltd',
            'customer_code' => 'CUST-5005',
        ]);
        $customerId = $customer->id;

        $product = Product::create([
            'product_name' => 'Chopped Red Onion',
            'product_code' => 'CRO-01',
            'standard_rate' => 195.00,
            'standard_cost' => 140.00,
            'uom' => 'KG',
            'is_active' => true,
        ]);
        $productId = $product->id;

        // Soft delete both records
        $customer->delete();
        $product->delete();
    });

    // 1. Recycle bin index displays soft-deleted records
    $binIndexRes = $this->actingAs($owner)->get("{$baseUrl}/organization/recycle-bin");
    $binIndexRes->assertStatus(200);
    $binIndexRes->assertSee('Heritage Foodworks Ltd');
    $binIndexRes->assertSee('Chopped Red Onion');

    // 2. Staff user without recycle_bin.restore cannot restore
    $unauthRestoreRes = $this->actingAs($staff)
        ->post("{$baseUrl}/organization/recycle-bin/restore", [
            'model_type' => 'customer',
            'id' => $customerId,
        ]);
    $unauthRestoreRes->assertStatus(403);

    // 3. Owner restores customer successfully
    $restoreRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/recycle-bin/restore", [
            'model_type' => 'customer',
            'id' => $customerId,
        ]);
    $restoreRes->assertStatus(302);
    $restoreRes->assertSessionHas('success');

    $this->tenant->run(function () use ($customerId) {
        $restoredCustomer = Customer::find($customerId);
        expect($restoredCustomer)->not->toBeNull();
        expect($restoredCustomer->deleted_at)->toBeNull();
    });

    // 4. Owner permanently purges product from recycle bin
    $purgeRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/recycle-bin/force-delete", [
            'model_type' => 'product',
            'id' => $productId,
        ]);
    $purgeRes->assertStatus(302);
    $purgeRes->assertSessionHas('success');

    $this->tenant->run(function () use ($productId) {
        $purgedProduct = Product::withTrashed()->find($productId);
        expect($purgedProduct)->toBeNull();
    });
});

test('bulk item update handles csv preview diff, batch confirmation, and grid update', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://salesinvoicingcorp.localhost';

    $product1Id = null;
    $product2Id = null;

    $this->tenant->run(function () use (&$product1Id, &$product2Id) {
        $p1 = Product::create([
            'product_name' => 'Onion Powder Commercial',
            'product_code' => 'OPC-01',
            'standard_rate' => 200.00,
            'standard_cost' => 150.00,
            'hsn_code' => '07129090',
            'packaging' => '25kg Paper Bag',
            'min_order_qty' => 100.0,
            'is_active' => true,
        ]);
        $product1Id = $p1->id;

        $p2 = Product::create([
            'product_name' => 'Fried Crispy Onion',
            'product_code' => 'FCO-01',
            'standard_rate' => 350.00,
            'standard_cost' => 260.00,
            'hsn_code' => '20059900',
            'packaging' => '15kg Carton',
            'min_order_qty' => 50.0,
            'is_active' => true,
        ]);
        $product2Id = $p2->id;
    });

    // 1. Test CSV Preview Diff
    $csvContent = "product_code,product_name,standard_rate,standard_cost,hsn_code,packaging,min_order_qty,is_active\n"
        ."OPC-01,Onion Powder Commercial,225.00,165.00,07129090,25kg Paper Bag,100,1\n"
        ."FCO-01,Fried Crispy Onion,380.00,285.00,20059900,15kg Carton,50,1\n";

    $tempCsv = UploadedFile::fake()->createWithContent('products_update.csv', $csvContent);

    $previewRes = $this->actingAs($owner)
        ->post("{$baseUrl}/products/bulk-update/preview", [
            'csv_file' => $tempCsv,
        ]);

    $previewRes->assertStatus(302);
    $previewRes->assertSessionHas('bulk_preview_data');

    // 2. Confirm CSV Bulk Updates
    $confirmRes = $this->actingAs($owner)
        ->post("{$baseUrl}/products/bulk-update/confirm");

    $confirmRes->assertStatus(302);
    $confirmRes->assertSessionHas('success');

    $this->tenant->run(function () use ($product1Id, $product2Id) {
        $p1 = Product::find($product1Id);
        expect((float) $p1->standard_rate)->toBe(225.00);
        expect((float) $p1->standard_cost)->toBe(165.00);

        $p2 = Product::find($product2Id);
        expect((float) $p2->standard_rate)->toBe(380.00);
        expect((float) $p2->standard_cost)->toBe(285.00);
    });

    // 3. Inline Grid Batch Update
    $gridUpdateRes = $this->actingAs($owner)
        ->post("{$baseUrl}/products/bulk-update/grid-update", [
            'products' => [
                [
                    'id' => $product1Id,
                    'standard_rate' => 240.00,
                    'standard_cost' => 170.00,
                    'hsn_code' => '07129090',
                    'min_order_qty' => 150.0,
                    'packaging' => '50kg HDPE Bag',
                ],
            ],
        ]);

    $gridUpdateRes->assertStatus(302);
    $gridUpdateRes->assertSessionHas('success');

    $this->tenant->run(function () use ($product1Id) {
        $p1 = Product::find($product1Id);
        expect((float) $p1->standard_rate)->toBe(240.00);
        expect((float) $p1->standard_cost)->toBe(170.00);
        expect($p1->packaging)->toBe('50kg HDPE Bag');
        expect((float) $p1->min_order_qty)->toBe(150.0);
    });
});
