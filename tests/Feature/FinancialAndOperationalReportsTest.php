<?php

declare(strict_types=1);

use App\Actions\ProvisionTenantAction;
use App\Models\ChartOfAccount;
use App\Models\CommercialShipment;
use App\Models\CommercialShipmentItem;
use App\Models\Customer;
use App\Models\Firm;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Sample;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\CompanyFirmContext;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['reportcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['reportcorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_reportcorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Bhavnagar Bio-Chemicals Ltd',
        'subdomain' => 'reportcorp',
        'owner_name' => 'Ketan Mehta',
        'owner_email' => 'ketan@bhavnagarbio.com',
        'password' => 'ReportSecret123!',
        'plan' => 'Standard',
        'gstin' => '24ABCDE1234F1Z5',
    ]);

    tenancy()->initialize($this->tenant);
    $this->owner = User::where('email', 'ketan@bhavnagarbio.com')->firstOrFail();
    $this->firm = Firm::first();

    // Create Customers
    $this->customerA = Customer::create([
        'customer_code' => 'CUST-REP-01',
        'company_name' => 'Sun Agro Commodities Ltd',
        'primary_contact_person' => 'Harish Shah',
        'primary_email' => 'harish@sunagro.com',
        'primary_phone' => '+919825011111',
        'address_line' => 'GIDC Estate, Vapi',
        'city' => 'Vapi',
        'state' => 'Gujarat',
        'state_code' => 24,
        'pincode' => '396195',
        'gst_number' => '24AAECS1111G1Z1',
        'outstanding_amount' => 450000.00,
        'status' => 'ACTIVE',
    ]);

    $this->customerB = Customer::create([
        'customer_code' => 'CUST-REP-02',
        'company_name' => 'Deccan Spice Exporters Pvt Ltd',
        'primary_contact_person' => 'Suresh Rao',
        'primary_email' => 'suresh@deccanspice.com',
        'primary_phone' => '+919848022222',
        'address_line' => 'APMC Yard, Guntur',
        'city' => 'Guntur',
        'state' => 'Andhra Pradesh',
        'state_code' => 37,
        'pincode' => '522004',
        'gst_number' => '37AAECD2222F1Z3',
        'outstanding_amount' => 120000.00,
        'status' => 'ACTIVE',
    ]);

    // Create Products
    $this->productA = Product::create([
        'product_name' => 'Organic White Onion Powder A-Grade',
        'product_code' => 'PRD-OP-01',
        'hsn_code' => '07122000',
        'unit' => 'KG',
        'base_price' => 200.00,
        'tax_rate' => 5.00,
        'status' => 'ACTIVE',
    ]);

    $this->productB = Product::create([
        'product_name' => 'Red Dehydrated Onion Flakes Export Quality',
        'product_code' => 'PRD-RF-01',
        'hsn_code' => '07122000',
        'unit' => 'KG',
        'base_price' => 150.00,
        'tax_rate' => 5.00,
        'status' => 'ACTIVE',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    CompanyFirmContext::flushCache();
});

test('statutory balance sheet computes assets, liabilities, equity and balances cleanly', function () {
    $accountingService = app(AccountingService::class);
    $reportService = app(ReportService::class);

    // Initial check on default seeded chart of accounts
    $bs = $reportService->getBalanceSheet();

    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['difference'])->toEqualWithDelta(0.0, 0.05)
        ->and($bs['assets']['grand_total'])->toBeGreaterThanOrEqual(0.0)
        ->and($bs['total_liabilities_and_equity'])->toEqualWithDelta($bs['assets']['grand_total'], 0.05);

    // Post a double-entry transaction: Debit Bank (101010) ₹100,000, Credit Share Capital (301010) ₹100,000
    $bankAcc = ChartOfAccount::where('account_code', '101010')->first();
    $capitalAcc = ChartOfAccount::where('account_code', '301010')->first();

    if ($bankAcc && $capitalAcc) {
        $accountingService->createVoucher([
            'voucher_type' => 'RECEIPT',
            'voucher_date' => Carbon::today()->format('Y-m-d'),
            'reference_number' => 'CAP-INFLOW-01',
            'narration' => 'Initial capital injection into operating bank account',
            'lines' => [
                ['account_id' => $bankAcc->id, 'type' => 'DEBIT', 'amount' => 100000.00],
                ['account_id' => $capitalAcc->id, 'type' => 'CREDIT', 'amount' => 100000.00],
            ],
        ]);
    }

    $updatedBs = $reportService->getBalanceSheet();
    expect($updatedBs['is_balanced'])->toBeTrue()
        ->and($updatedBs['difference'])->toEqualWithDelta(0.0, 0.05)
        ->and($updatedBs['assets']['grand_total'])->toEqualWithDelta($updatedBs['total_liabilities_and_equity'], 0.05);

    // Test Web View
    $response = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/balance-sheet');

    $response->assertOk()
        ->assertSee('Statutory Balance Sheet')
        ->assertSee('Statement Balanced')
        ->assertSee('Current Assets');
});

test('bill-wise profit report calculates accurate gross profit, margin percentages, and health categorizations', function () {
    $reportService = app(ReportService::class);

    // Invoice 1: High Margin (Revenue: 200,000, Cost: 140,000 => Profit: 60,000, Margin: 30% -> HIGH)
    $inv1 = Invoice::create([
        'invoice_number' => 'INV-2026-001',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::now()->subDays(5),
        'due_date' => Carbon::now()->addDays(25),
        'currency' => 'INR',
        'subtotal' => 200000.00,
        'tax_total' => 10000.00,
        'total_amount' => 210000.00,
        'total_cost_amount' => 140000.00,
        'gross_profit_amount' => 60000.00,
        'gross_profit_percentage' => 30.00,
        'amount_received' => 0.00,
        'balance_due' => 210000.00,
        'status' => 'ISSUED',
    ]);

    // Invoice 2: Healthy Margin (Revenue: 100,000, Cost: 85,000 => Profit: 15,000, Margin: 15% -> HEALTHY)
    $inv2 = Invoice::create([
        'invoice_number' => 'INV-2026-002',
        'customer_id' => $this->customerB->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::now()->subDays(3),
        'due_date' => Carbon::now()->addDays(27),
        'currency' => 'INR',
        'subtotal' => 100000.00,
        'tax_total' => 5000.00,
        'total_amount' => 105000.00,
        'total_cost_amount' => 85000.00,
        'gross_profit_amount' => 15000.00,
        'gross_profit_percentage' => 15.00,
        'amount_received' => 50000.00,
        'balance_due' => 55000.00,
        'status' => 'PARTIALLY_PAID',
    ]);

    // Invoice 3: Low Margin (Revenue: 50,000, Cost: 48,000 => Profit: 2,000, Margin: 4% -> LOW)
    $inv3 = Invoice::create([
        'invoice_number' => 'INV-2026-003',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::now()->subDays(1),
        'due_date' => Carbon::now()->addDays(29),
        'currency' => 'INR',
        'subtotal' => 50000.00,
        'tax_total' => 2500.00,
        'total_amount' => 52500.00,
        'total_cost_amount' => 48000.00,
        'gross_profit_amount' => 2000.00,
        'gross_profit_percentage' => 4.00,
        'amount_received' => 0.00,
        'balance_due' => 52500.00,
        'status' => 'ISSUED',
    ]);

    $report = $reportService->getBillProfitReport();

    expect($report['summary']['total_invoices'])->toBe(3)
        ->and($report['summary']['total_revenue'])->toEqualWithDelta(350000.00, 0.01)
        ->and($report['summary']['total_cost'])->toEqualWithDelta(273000.00, 0.01)
        ->and($report['summary']['total_profit'])->toEqualWithDelta(77000.00, 0.01)
        ->and($report['summary']['high_margin_count'])->toBe(1)
        ->and($report['summary']['healthy_margin_count'])->toBe(1)
        ->and($report['summary']['low_margin_count'])->toBe(1)
        ->and($report['summary']['negative_margin_count'])->toBe(0);

    // Test Web View with filter
    $response = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/bill-profit?min_margin=10');

    $response->assertOk()
        ->assertSee('Bill-Wise Profit')
        ->assertSee('INV-2026-001')
        ->assertSee('INV-2026-002')
        ->assertDontSee('INV-2026-003'); // Filtered out by min_margin=10
});

test('party-wise profitability ranks customers by profit contribution and computes outstanding balances', function () {
    $reportService = app(ReportService::class);

    // Create invoices for Customer A
    Invoice::create([
        'invoice_number' => 'INV-PA-01',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::now()->subDays(10),
        'due_date' => Carbon::now()->addDays(20),
        'currency' => 'INR',
        'subtotal' => 500000.00,
        'tax_total' => 25000.00,
        'total_amount' => 525000.00,
        'total_cost_amount' => 350000.00,
        'gross_profit_amount' => 150000.00,
        'gross_profit_percentage' => 30.00,
        'amount_received' => 0.00,
        'balance_due' => 525000.00,
        'status' => 'ISSUED',
    ]);

    // Create invoices for Customer B
    Invoice::create([
        'invoice_number' => 'INV-PB-01',
        'customer_id' => $this->customerB->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::now()->subDays(5),
        'due_date' => Carbon::now()->addDays(25),
        'currency' => 'INR',
        'subtotal' => 100000.00,
        'tax_total' => 5000.00,
        'total_amount' => 105000.00,
        'total_cost_amount' => 80000.00,
        'gross_profit_amount' => 20000.00,
        'gross_profit_percentage' => 20.00,
        'amount_received' => 0.00,
        'balance_due' => 105000.00,
        'status' => 'ISSUED',
    ]);

    $report = $reportService->getPartyProfitReport();

    expect($report['summary']['total_parties'])->toBe(2)
        ->and($report['summary']['total_profit'])->toEqualWithDelta(170000.00, 0.01)
        ->and($report['summary']['top_party']['company_name'])->toBe('Sun Agro Commodities Ltd')
        ->and($report['parties'][0]['customer_id'])->toBe($this->customerA->id)
        ->and($report['parties'][0]['gross_profit'])->toEqualWithDelta(150000.00, 0.01)
        ->and($report['parties'][0]['profit_contribution'])->toBeGreaterThan(80.0);

    // Test Web View
    $response = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/party-profit');

    $response->assertOk()
        ->assertSee('Party-Wise Profitability')
        ->assertSee('Sun Agro Commodities Ltd')
        ->assertSee('Deccan Spice Exporters Pvt Ltd');
});

test('ar aging report categorizes receivables into correct aging buckets and calculates totals', function () {
    $reportService = app(ReportService::class);
    $asOf = Carbon::today();

    // 1. Current (Due 10 days in future) -> 'CURRENT'
    Invoice::create([
        'invoice_number' => 'INV-AGE-CURR',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => $asOf->copy()->subDays(10),
        'due_date' => $asOf->copy()->addDays(10),
        'currency' => 'INR',
        'subtotal' => 40000.00,
        'tax_total' => 2000.00,
        'total_amount' => 42000.00,
        'amount_received' => 0.00,
        'balance_due' => 42000.00,
        'status' => 'ISSUED',
    ]);

    // 2. 1-30 Days Overdue (Due 15 days ago) -> 'DAYS_0_30'
    Invoice::create([
        'invoice_number' => 'INV-AGE-30',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => $asOf->copy()->subDays(45),
        'due_date' => $asOf->copy()->subDays(15),
        'currency' => 'INR',
        'subtotal' => 60000.00,
        'tax_total' => 3000.00,
        'total_amount' => 63000.00,
        'amount_received' => 0.00,
        'balance_due' => 63000.00,
        'status' => 'ISSUED',
    ]);

    // 3. 31-60 Days Overdue (Due 45 days ago) -> 'DAYS_31_60'
    Invoice::create([
        'invoice_number' => 'INV-AGE-60',
        'customer_id' => $this->customerB->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => $asOf->copy()->subDays(75),
        'due_date' => $asOf->copy()->subDays(45),
        'currency' => 'INR',
        'subtotal' => 30000.00,
        'tax_total' => 1500.00,
        'total_amount' => 31500.00,
        'amount_received' => 0.00,
        'balance_due' => 31500.00,
        'status' => 'OVERDUE',
    ]);

    // 4. 61-90 Days Overdue (Due 75 days ago) -> 'DAYS_61_90'
    Invoice::create([
        'invoice_number' => 'INV-AGE-90',
        'customer_id' => $this->customerB->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => $asOf->copy()->subDays(105),
        'due_date' => $asOf->copy()->subDays(75),
        'currency' => 'INR',
        'subtotal' => 20000.00,
        'tax_total' => 1000.00,
        'total_amount' => 21000.00,
        'amount_received' => 0.00,
        'balance_due' => 21000.00,
        'status' => 'OVERDUE',
    ]);

    // 5. 90+ Days Critical Overdue (Due 110 days ago) -> 'DAYS_90_PLUS'
    Invoice::create([
        'invoice_number' => 'INV-AGE-CRIT',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => $asOf->copy()->subDays(140),
        'due_date' => $asOf->copy()->subDays(110),
        'currency' => 'INR',
        'subtotal' => 50000.00,
        'tax_total' => 2500.00,
        'total_amount' => 52500.00,
        'amount_received' => 0.00,
        'balance_due' => 52500.00,
        'status' => 'OVERDUE',
    ]);

    $report = $reportService->getArAgingReport(['as_of_date' => $asOf->format('Y-m-d')]);

    expect($report['summary']['invoices_count'])->toBe(5)
        ->and($report['summary']['total_outstanding'])->toEqualWithDelta(210000.00, 0.01)
        ->and($report['summary']['current_total'])->toEqualWithDelta(42000.00, 0.01)
        ->and($report['summary']['days_0_30_total'])->toEqualWithDelta(63000.00, 0.01)
        ->and($report['summary']['days_31_60_total'])->toEqualWithDelta(31500.00, 0.01)
        ->and($report['summary']['days_61_90_total'])->toEqualWithDelta(21000.00, 0.01)
        ->and($report['summary']['days_90_plus_total'])->toEqualWithDelta(52500.00, 0.01);

    // Test Web View
    $response = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/ar-aging');

    $response->assertOk()
        ->assertSee('Accounts Receivable (AR) Aging')
        ->assertSee('Current (Not Due)')
        ->assertSee('90+ Days Critical')
        ->assertSee('Sun Agro Commodities Ltd');
});

test('batch and serial traceability traces raw lot through production batch, qc samples, dispatches, and invoices', function () {
    $reportService = app(ReportService::class);

    // 1. Create Production Batch with raw lot
    $batch = ProductionBatch::create([
        'batch_number' => 'BAT-2026-B01',
        'product_id' => $this->productA->id,
        'production_date' => Carbon::now()->subDays(10),
        'batch_qty' => 5000.00,
        'available_qty' => 5000.00,
        'raw_lot_number' => 'RAW-LOT-ONION-99',
        'status' => 'COMPLETED',
    ]);

    // 2. Create QC Sample trial linked to batch
    $sample = Sample::create([
        'sample_number' => 'SMP-BAT-01',
        'customer_id' => $this->customerA->id,
        'product_id' => $this->productA->id,
        'batch_number' => $batch->batch_number,
        'quantity' => 2.50,
        'trial_status' => 'APPROVED',
        'trial_result' => 'Meets strict export moisture specs (< 6%)',
    ]);

    // 3. Create Sales Order and Commercial Shipment with batch item
    $order = SalesOrder::create([
        'order_number' => 'SO-BAT-2026-01',
        'customer_id' => $this->customerA->id,
        'order_date' => Carbon::now()->subDays(6),
        'status' => 'CONFIRMED',
        'currency' => 'INR',
        'subtotal' => 300000.00,
        'tax_total' => 15000.00,
        'grand_total' => 315000.00,
    ]);

    $orderItem = SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'order_qty' => 1500.00,
        'shipped_qty' => 1500.00,
        'balance_qty' => 0.00,
        'rate' => 200.00,
        'order_value' => 300000.00,
    ]);

    $shipment = CommercialShipment::create([
        'shipment_number' => 'SHP-2026-001',
        'sales_order_id' => $order->id,
        'lr_number' => 'LR-GUJ-9988',
        'firm_id' => $this->firm?->id,
        'transporter' => 'Western Freight Carrier',
        'vehicle_number' => 'GJ04TR8899',
        'shipment_date' => Carbon::now()->subDays(4),
        'status' => 'DELIVERED',
    ]);

    CommercialShipmentItem::create([
        'commercial_shipment_id' => $shipment->id,
        'sales_order_item_id' => $orderItem->id,
        'batch_number' => $batch->batch_number,
        'quantity' => 1500.00,
        'unit_value' => 200.00,
        'total_value' => 300000.00,
    ]);

    // 4. Create Tax Invoice linked to shipment
    $invoice = Invoice::create([
        'invoice_number' => 'INV-BAT-2026-01',
        'customer_id' => $this->customerA->id,
        'firm_id' => $this->firm?->id,
        'commercial_shipment_id' => $shipment->id,
        'invoice_date' => Carbon::now()->subDays(3),
        'due_date' => Carbon::now()->addDays(27),
        'currency' => 'INR',
        'subtotal' => 300000.00,
        'tax_total' => 15000.00,
        'total_amount' => 315000.00,
        'total_cost_amount' => 210000.00,
        'gross_profit_amount' => 90000.00,
        'gross_profit_percentage' => 30.00,
        'amount_received' => 0.00,
        'balance_due' => 315000.00,
        'status' => 'ISSUED',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->productA->id,
        'description' => 'Organic White Onion Powder A-Grade',
        'batch_number' => $batch->batch_number,
        'quantity' => 1500.00,
        'rate' => 200.00,
        'gst_rate' => 5.00,
        'gst_amount' => 15000.00,
        'amount' => 300000.00,
    ]);

    $report = $reportService->getBatchTraceReport('BAT-2026-B01');

    expect($report['batches_count'])->toBe(1)
        ->and($report['traces'])->toHaveCount(1);

    $trace = $report['traces'][0];
    expect($trace['raw_lot'])->toBe('RAW-LOT-ONION-99')
        ->and($trace['batch']->batch_number)->toBe('BAT-2026-B01')
        ->and($trace['samples'])->toHaveCount(1)
        ->and($trace['shipments'])->toHaveCount(1)
        ->and($trace['invoices'])->toHaveCount(1)
        ->and($trace['has_full_chain'])->toBeTrue();

    // Test Web View
    $response = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/batch-trace?search=BAT-2026-B01');

    $response->assertOk()
        ->assertSee('BAT-2026-B01')
        ->assertSee('RAW-LOT-ONION-99')
        ->assertSee('LR-GUJ-9988')
        ->assertSee('INV-BAT-2026-01');
});

test('all financial and operational report export endpoints generate valid downloads', function () {
    // 1. Reports Command Hub Index
    $hubResponse = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports');
    $hubResponse->assertOk()
        ->assertSee('Reports Hub')
        ->assertSee('Statutory Balance Sheet')
        ->assertSee('Bill-Wise Profit')
        ->assertSee('Batch Traceability');

    // 2. Balance Sheet Exports
    $bsExcel = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/balance-sheet/excel');
    $bsExcel->assertOk()
        ->assertHeader('content-disposition');

    $bsPdf = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/balance-sheet/pdf');
    $bsPdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // 3. Bill Profit Exports
    $billExcel = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/bill-profit/excel');
    $billExcel->assertOk()
        ->assertHeader('content-disposition');

    $billPdf = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/bill-profit/pdf');
    $billPdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // 4. Party Profit Exports
    $partyExcel = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/party-profit/excel');
    $partyExcel->assertOk()
        ->assertHeader('content-disposition');

    $partyPdf = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/party-profit/pdf');
    $partyPdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // 5. AR Aging Exports
    $arExcel = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/ar-aging/excel');
    $arExcel->assertOk()
        ->assertHeader('content-disposition');

    $arPdf = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/ar-aging/pdf');
    $arPdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // 6. Batch Trace Exports
    $batchExcel = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/batch-trace/excel');
    $batchExcel->assertOk()
        ->assertHeader('content-disposition');

    $batchPdf = $this->actingAs($this->owner)
        ->get('http://reportcorp.localhost/reports/batch-trace/pdf');
    $batchPdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
