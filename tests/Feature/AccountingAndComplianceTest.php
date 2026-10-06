<?php

use App\Actions\ProvisionTenantAction;
use App\Exceptions\UnbalancedJournalException;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Firm;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\JournalVoucher;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountingService;
use App\Services\CompanyFirmContext;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['acctestcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['acctestcorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_acctestcorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Mahuva Agro Processing Ltd',
        'subdomain' => 'acctestcorp',
        'owner_name' => 'Sanjay Patel',
        'owner_email' => 'sanjay@mahuvaagro.com',
        'password' => 'OwnerPassword123!',
        'plan' => 'Standard',
        'gstin' => '24AABCM1234F1Z8',
    ]);

    tenancy()->initialize($this->tenant);
    $this->owner = User::where('email', 'sanjay@mahuvaagro.com')->firstOrFail();
    $this->firm = Firm::first();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    try {
        DB::table('domains')->whereIn('tenant_id', ['acctestcorp'])->delete();
        DB::table('tenants')->whereIn('id', ['acctestcorp'])->delete();
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_acctestcorp`');
    } catch (Throwable $e) {
        // Ignore
    }
});

test('standard chart of accounts is seeded for new tenant', function () {
    $expectedAccounts = [
        '1010' => 'Cash Account',
        '1020' => 'Bank Account (Current)',
        '1030' => 'Sundry Debtors (Receivables)',
        '1041' => 'Input CGST Ledger',
        '1042' => 'Input SGST Ledger',
        '1043' => 'Input IGST Ledger',
        '1050' => 'Fixed Assets (Plant & Machinery)',
        '2010' => 'Sundry Creditors (Payables)',
        '2021' => 'Output CGST Ledger',
        '2022' => 'Output SGST Ledger',
        '2023' => 'Output IGST Ledger',
        '2030' => 'TCS Payable (206C 1H)',
        '3010' => 'Owner Capital',
        '4010' => 'Sales Revenue',
        '5010' => 'Purchase Account',
        '5020' => 'Freight Inward / Cartage',
        '5030' => 'Depreciation Expense',
        '5040' => 'General & Administrative Expenses',
    ];

    foreach ($expectedAccounts as $code => $name) {
        $acc = ChartOfAccount::where('account_code', $code)->first();
        expect($acc)->not->toBeNull()
            ->and($acc->account_name)->toBe($name)
            ->and($acc->is_system)->toBeTrue();
    }
});

test('double-entry balance invariant is strictly enforced', function () {
    $accountingService = app(AccountingService::class);
    $cash = ChartOfAccount::where('account_code', '1010')->firstOrFail();
    $bank = ChartOfAccount::where('account_code', '1020')->firstOrFail();

    // 1. Unbalanced voucher must throw UnbalancedJournalException
    expect(function () use ($accountingService, $cash, $bank) {
        $accountingService->createJournalVoucher([
            'firm_id' => $this->firm->id,
            'voucher_date' => now(),
            'voucher_type' => 'CONTRA',
            'narration' => 'Unbalanced test',
        ], [
            ['account_id' => $cash->id, 'debit_amount' => 5000.00, 'credit_amount' => 0.00],
            ['account_id' => $bank->id, 'debit_amount' => 0.00, 'credit_amount' => 4500.00], // 500 mismatch
        ]);
    })->toThrow(UnbalancedJournalException::class);

    // 2. Balanced voucher succeeds and updates ledger balances
    $initialCash = (float) $cash->current_balance;
    $initialBank = (float) $bank->current_balance;

    $voucher = $accountingService->createJournalVoucher([
        'firm_id' => $this->firm->id,
        'voucher_date' => now(),
        'voucher_type' => 'CONTRA',
        'narration' => 'Cash withdrawal from bank for petty cash',
    ], [
        ['account_id' => $cash->id, 'debit_amount' => 5000.00, 'credit_amount' => 0.00],
        ['account_id' => $bank->id, 'debit_amount' => 0.00, 'credit_amount' => 5000.00],
    ]);

    expect($voucher)->toBeInstanceOf(JournalVoucher::class)
        ->and($voucher->isBalanced())->toBeTrue()
        ->and((float) $voucher->total_debit)->toBe(5000.00)
        ->and((float) $voucher->total_credit)->toBe(5000.00);

    // Verify balances updated: Cash increased by 5000, Bank decreased by 5000
    $cash->refresh();
    $bank->refresh();

    expect((float) $cash->current_balance)->toBe(round($initialCash + 5000.00, 2))
        ->and((float) $bank->current_balance)->toBe(round($initialBank - 5000.00, 2));
});

test('invoice creation automatically posts balanced sales journal voucher', function () {
    $accountingService = app(AccountingService::class);

    $customer = Customer::create([
        'customer_code' => 'CUST-ACC-01',
        'company_name' => 'Kisan Agro Foods',
        'state' => 'Gujarat',
        'state_code' => '24',
        'credit_limit' => 500000.00,
        'credit_limit_action' => 'WARN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    // Create Tax Invoice
    $invoice = Invoice::create([
        'firm_id' => $this->firm->id,
        'invoice_number' => 'INV-ACC-2026-001',
        'customer_id' => $customer->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'material_subtotal' => 100000.00,
        'freight_amount' => 5000.00,
        'taxable_amount' => 105000.00,
        'subtotal' => 105000.00,
        'gst_rate' => 5.00,
        'cgst_amount' => 2625.00,
        'sgst_amount' => 2625.00,
        'igst_amount' => 0.00,
        'tcs_rate' => 0.10,
        'tcs_amount' => 110.25,
        'total_amount' => 110360.25,
        'amount_received' => 0.00,
        'balance_due' => 110360.25,
        'status' => 'ISSUED',
    ]);

    $voucher = $accountingService->postFromInvoice($invoice);

    expect($voucher)->toBeInstanceOf(JournalVoucher::class)
        ->and($voucher->voucher_type)->toBe('SALES')
        ->and($voucher->isBalanced())->toBeTrue()
        ->and((float) $voucher->total_debit)->toBe(110360.25)
        ->and((float) $voucher->total_credit)->toBe(110360.25);

    // Verify Sundry Debtors ledger increased
    $debtors = ChartOfAccount::where('account_code', '1030')->firstOrFail();
    expect((float) $debtors->current_balance)->toBe(110360.25);

    // Verify Sales Revenue ledger
    $sales = ChartOfAccount::where('account_code', '4010')->firstOrFail();
    expect((float) $sales->current_balance)->toBe(105000.00);

    // Verify Output GST & TCS
    $cgst = ChartOfAccount::where('account_code', '2021')->firstOrFail();
    $sgst = ChartOfAccount::where('account_code', '2022')->firstOrFail();
    $tcs = ChartOfAccount::where('account_code', '2030')->firstOrFail();

    expect((float) $cgst->current_balance)->toBe(2625.00)
        ->and((float) $sgst->current_balance)->toBe(2625.00)
        ->and((float) $tcs->current_balance)->toBe(110.25);
});

test('payment receipt automatically posts receipt journal voucher', function () {
    $accountingService = app(AccountingService::class);

    $customer = Customer::create([
        'customer_code' => 'CUST-ACC-02',
        'company_name' => 'Patel Spices & Food',
        'state' => 'Gujarat',
        'state_code' => '24',
        'credit_limit' => 200000.00,
        'credit_limit_action' => 'NONE',
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'firm_id' => $this->firm->id,
        'invoice_number' => 'INV-ACC-2026-002',
        'customer_id' => $customer->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'material_subtotal' => 50000.00,
        'taxable_amount' => 50000.00,
        'cgst_amount' => 1250.00,
        'sgst_amount' => 1250.00,
        'total_amount' => 52500.00,
        'balance_due' => 52500.00,
        'status' => 'ISSUED',
    ]);

    // Initial invoice posting
    $accountingService->postFromInvoice($invoice);

    // Record Payment Receipt
    $receipt = PaymentReceipt::create([
        'receipt_number' => 'RCP-ACC-001',
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'receipt_date' => now(),
        'amount_received' => 52500.00,
        'payment_mode' => 'RTGS',
        'reference_number' => 'UTR1234567890',
        'remarks' => 'Full RTGS payment received',
    ]);

    $voucher = $accountingService->postFromReceipt($receipt);

    expect($voucher)->toBeInstanceOf(JournalVoucher::class)
        ->and($voucher->voucher_type)->toBe('RECEIPT')
        ->and($voucher->isBalanced())->toBeTrue()
        ->and((float) $voucher->total_debit)->toBe(52500.00)
        ->and((float) $voucher->total_credit)->toBe(52500.00);

    // Debtors balance should now be 0.00
    $debtors = ChartOfAccount::where('account_code', '1030')->firstOrFail();
    expect((float) $debtors->current_balance)->toBe(0.00);

    // Bank Account should have increased by 52500.00
    $bank = ChartOfAccount::where('account_code', '1020')->firstOrFail();
    expect((float) $bank->current_balance)->toBe(52500.00);
});

test('expense with itc splits taxes and updates itc register', function () {
    $accountingService = app(AccountingService::class);

    $vendor = Vendor::create([
        'company_name' => 'Maruti Packing Solutions',
        'gstin' => '24AABCM9999F1Z1',
        'state' => 'Gujarat',
        'is_active' => true,
    ]);

    $expense = Expense::create([
        'firm_id' => $this->firm->id,
        'vendor_id' => $vendor->id,
        'expense_category' => 'Packaging Material',
        'vendor_invoice_number' => 'MPS/26/104',
        'expense_date' => now(),
        'base_amount' => 20000.00,
        'gst_rate' => 18.00,
        'cgst_amount' => 1800.00,
        'sgst_amount' => 1800.00,
        'igst_amount' => 0.00,
        'total_amount' => 23600.00,
        'is_itc_eligible' => true,
        'itc_type' => 'INPUT_GOODS',
        'payment_status' => 'PAID',
    ]);

    $voucher = $accountingService->postFromExpense($expense, 'BANK');

    expect($voucher)->toBeInstanceOf(JournalVoucher::class)
        ->and($voucher->isBalanced())->toBeTrue()
        ->and((float) $voucher->total_debit)->toBe(23600.00)
        ->and((float) $voucher->total_credit)->toBe(23600.00);

    // Verify Input Taxes have debited
    $cgstInput = ChartOfAccount::where('account_code', '1041')->firstOrFail();
    $sgstInput = ChartOfAccount::where('account_code', '1042')->firstOrFail();

    expect((float) $cgstInput->current_balance)->toBe(1800.00)
        ->and((float) $sgstInput->current_balance)->toBe(1800.00);
});

test('expense with ineligible itc capitalizes tax to expense', function () {
    $accountingService = app(AccountingService::class);

    $expense = Expense::create([
        'firm_id' => $this->firm->id,
        'expense_category' => 'Office Refreshments',
        'expense_date' => now(),
        'base_amount' => 5000.00,
        'gst_rate' => 18.00,
        'cgst_amount' => 450.00,
        'sgst_amount' => 450.00,
        'igst_amount' => 0.00,
        'total_amount' => 5900.00,
        'is_itc_eligible' => false,
        'itc_type' => 'INELIGIBLE',
        'payment_status' => 'PAID',
    ]);

    $voucher = $accountingService->postFromExpense($expense, 'CASH');

    expect($voucher)->toBeInstanceOf(JournalVoucher::class)
        ->and($voucher->isBalanced())->toBeTrue()
        ->and((float) $voucher->total_debit)->toBe(5900.00)
        ->and((float) $voucher->total_credit)->toBe(5900.00);

    // Full 5900 should be debited to expense account, NOT input tax
    $expAccount = ChartOfAccount::where('account_code', '5040')->firstOrFail();
    expect((float) $expAccount->current_balance)->toBe(5900.00);
});

test('fixed asset slm depreciation calculates accurately and creates depreciation voucher', function () {
    $depService = app(DepreciationService::class);

    // 1. SLM Method: Cost ₹1,00,000, Salvage ₹10,000, Useful Life 10 Years -> Annual Depr = ₹9,000
    $asset = FixedAsset::create([
        'firm_id' => $this->firm->id,
        'asset_code' => 'FA-PLANT-001',
        'asset_name' => 'Dehydration Rotary Dryer #1',
        'asset_category' => 'PLANT_MACHINERY',
        'purchase_date' => Carbon::now()->subYear(),
        'purchase_cost' => 100000.00,
        'salvage_value' => 10000.00,
        'useful_life_years' => 10,
        'depreciation_method' => 'SLM',
        'depreciation_rate' => 10.00,
        'accumulated_depreciation' => 0.00,
        'current_book_value' => 100000.00,
        'status' => 'ACTIVE',
    ]);

    $calcAnnual = $depService->calculateDepreciation($asset, now(), 'YEAR');
    expect($calcAnnual)->toBe(9000.00);

    // Post depreciation
    $depRecord = $depService->postDepreciationVoucher($asset, $calcAnnual, now());

    expect($depRecord)->not->toBeNull()
        ->and((float) $depRecord->depreciation_amount)->toBe(9000.00)
        ->and((float) $depRecord->book_value_before)->toBe(100000.00)
        ->and((float) $depRecord->book_value_after)->toBe(91000.00);

    // Verify Asset updated
    $asset->refresh();
    expect((float) $asset->accumulated_depreciation)->toBe(9000.00)
        ->and((float) $asset->current_book_value)->toBe(91000.00);

    // Verify balanced depreciation voucher
    $voucher = $depRecord->journalVoucher;
    expect($voucher)->not->toBeNull()
        ->and($voucher->voucher_type)->toBe('DEPRECIATION')
        ->and($voucher->isBalanced())->toBeTrue();

    // Verify accounts: Depreciation Expense (5030) Debited, Fixed Assets (1050) Credited
    $depExpAcc = ChartOfAccount::where('account_code', '5030')->firstOrFail();
    $fixedAssetAcc = ChartOfAccount::where('account_code', '1050')->firstOrFail();

    expect((float) $depExpAcc->current_balance)->toBe(9000.00)
        ->and((float) $fixedAssetAcc->current_balance)->toBe(-9000.00); // Credit reduces debit-normal asset
});

test('fixed asset wdv depreciation calculates accurately', function () {
    $depService = app(DepreciationService::class);

    // WDV Method: Cost ₹50,000, Rate 20% -> Depr = ₹10,000 -> Book Value = ₹40,000
    $asset = FixedAsset::create([
        'firm_id' => $this->firm->id,
        'asset_code' => 'FA-VEHICLE-001',
        'asset_name' => 'Tata Ace Delivery Van',
        'asset_category' => 'VEHICLES',
        'purchase_date' => Carbon::now()->subYear(),
        'purchase_cost' => 50000.00,
        'salvage_value' => 5000.00,
        'useful_life_years' => 5,
        'depreciation_method' => 'WDV',
        'depreciation_rate' => 20.00,
        'accumulated_depreciation' => 0.00,
        'current_book_value' => 50000.00,
        'status' => 'ACTIVE',
    ]);

    $calc = $depService->calculateDepreciation($asset, now(), 'YEAR');
    expect($calc)->toBe(10000.00);

    $depRecord = $depService->postDepreciationVoucher($asset, $calc, now());

    expect($depRecord)->not->toBeNull()
        ->and((float) $depRecord->book_value_after)->toBe(40000.00);

    $asset->refresh();
    expect((float) $asset->current_book_value)->toBe(40000.00);
});

test('ledger statement generates running balance correctly', function () {
    $accountingService = app(AccountingService::class);
    $cash = ChartOfAccount::where('account_code', '1010')->firstOrFail();
    $bank = ChartOfAccount::where('account_code', '1020')->firstOrFail();

    // Transaction 1: Deposit ₹10,000 into Bank from Cash
    $accountingService->createJournalVoucher([
        'firm_id' => $this->firm->id,
        'voucher_date' => now()->subDays(5),
        'voucher_type' => 'CONTRA',
        'narration' => 'Cash deposit',
    ], [
        ['account_id' => $bank->id, 'debit_amount' => 10000.00, 'credit_amount' => 0.00],
        ['account_id' => $cash->id, 'debit_amount' => 0.00, 'credit_amount' => 10000.00],
    ]);

    // Transaction 2: Deposit another ₹5,000 into Bank from Cash
    $accountingService->createJournalVoucher([
        'firm_id' => $this->firm->id,
        'voucher_date' => now()->subDays(2),
        'voucher_type' => 'CONTRA',
        'narration' => 'Second cash deposit',
    ], [
        ['account_id' => $bank->id, 'debit_amount' => 5000.00, 'credit_amount' => 0.00],
        ['account_id' => $cash->id, 'debit_amount' => 0.00, 'credit_amount' => 5000.00],
    ]);

    $statement = $accountingService->getLedgerStatement($bank->id);

    expect($statement['total_debit'])->toBe(15000.00)
        ->and($statement['total_credit'])->toBe(0.00)
        ->and($statement['closing_balance'])->toBe(15000.00)
        ->and(count($statement['transactions']))->toBe(2);
});

test('trial balance verifies that grand debits equal grand credits', function () {
    $accountingService = app(AccountingService::class);

    // Seed multiple balanced transactions
    $cash = ChartOfAccount::where('account_code', '1010')->firstOrFail();
    $bank = ChartOfAccount::where('account_code', '1020')->firstOrFail();
    $capital = ChartOfAccount::where('account_code', '3010')->firstOrFail();

    // Owner capital introduced: Dr. Bank ₹50,000, Cr. Owner Capital ₹50,000
    $accountingService->createJournalVoucher([
        'firm_id' => $this->firm->id,
        'voucher_date' => now(),
        'voucher_type' => 'RECEIPT',
        'narration' => 'Initial capital infusion',
    ], [
        ['account_id' => $bank->id, 'debit_amount' => 50000.00, 'credit_amount' => 0.00],
        ['account_id' => $capital->id, 'debit_amount' => 0.00, 'credit_amount' => 50000.00],
    ]);

    $trialBalance = $accountingService->getTrialBalance($this->firm->id);

    expect($trialBalance['is_balanced'])->toBeTrue()
        ->and($trialBalance['grand_debit'])->toBe($trialBalance['grand_credit'])
        ->and($trialBalance['grand_debit'])->toBeGreaterThan(0.00);
});
