<?php

namespace App\Http\Controllers;

use App\Exceptions\UnbalancedJournalException;
use App\Models\ChartOfAccount;
use App\Models\JournalVoucher;
use App\Services\AccountingService;
use App\Services\CompanyFirmContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountingController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Chart of Accounts view grouped by ASSETS, LIABILITIES, EQUITY, INCOME, EXPENSES.
     */
    public function chart(Request $request)
    {
        $groupFilter = $request->query('group');
        $search = $request->query('search');

        $query = ChartOfAccount::query();

        if ($groupFilter) {
            $query->where('account_group', strtoupper($groupFilter));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('account_code', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('sub_group', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('account_code')->get();

        $groupedAccounts = [
            'ASSETS' => $accounts->where('account_group', 'ASSETS'),
            'LIABILITIES' => $accounts->where('account_group', 'LIABILITIES'),
            'EQUITY' => $accounts->where('account_group', 'EQUITY'),
            'INCOME' => $accounts->where('account_group', 'INCOME'),
            'EXPENSES' => $accounts->where('account_group', 'EXPENSES'),
        ];

        $totals = [
            'assets' => $groupedAccounts['ASSETS']->sum('current_balance'),
            'liabilities' => $groupedAccounts['LIABILITIES']->sum('current_balance'),
            'equity' => $groupedAccounts['EQUITY']->sum('current_balance'),
            'income' => $groupedAccounts['INCOME']->sum('current_balance'),
            'expenses' => $groupedAccounts['EXPENSES']->sum('current_balance'),
        ];

        return view('accounting.chart', compact('groupedAccounts', 'totals', 'groupFilter', 'search'));
    }

    /**
     * Creates a new custom Chart of Account entry.
     */
    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:50|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:150',
            'account_group' => 'required|string|in:ASSETS,LIABILITIES,EQUITY,INCOME,EXPENSES',
            'sub_group' => 'required|string|max:50',
            'opening_balance' => 'nullable|numeric',
        ]);

        $opening = round((float) ($validated['opening_balance'] ?? 0.0), 2);

        $account = ChartOfAccount::create([
            'account_code' => $validated['account_code'],
            'account_name' => $validated['account_name'],
            'account_group' => $validated['account_group'],
            'sub_group' => strtoupper($validated['sub_group']),
            'opening_balance' => $opening,
            'current_balance' => $opening,
            'is_system' => false,
            'is_active' => true,
        ]);

        return redirect()->route('accounting.chart')->with('success', "✓ Account [{$account->account_code}] {$account->account_name} created successfully.");
    }

    /**
     * Lists Journal Vouchers.
     */
    public function vouchers(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $voucherType = $request->query('type');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = JournalVoucher::with(['firm', 'items.account'])
            ->orderBy('voucher_date', 'desc')
            ->orderBy('id', 'desc');

        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        if ($voucherType) {
            $query->where('voucher_type', strtoupper($voucherType));
        }

        if ($fromDate) {
            $query->where('voucher_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('voucher_date', '<=', $toDate);
        }

        $vouchers = $query->paginate(25)->withQueryString();
        $accounts = ChartOfAccount::active()->orderBy('account_code')->get();

        return view('accounting.vouchers', compact('vouchers', 'accounts', 'voucherType', 'fromDate', 'toDate'));
    }

    /**
     * Stores a new manual double-entry Journal Voucher.
     */
    public function storeVoucher(Request $request)
    {
        $validated = $request->validate([
            'voucher_date' => 'required|date',
            'voucher_type' => 'required|string|in:JOURNAL,SALES,PURCHASE,PAYMENT,RECEIPT,CONTRA,DEPRECIATION',
            'narration' => 'nullable|string|max:500',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.debit_amount' => 'nullable|numeric|min:0',
            'items.*.credit_amount' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        try {
            $voucher = $this->accountingService->createJournalVoucher([
                'firm_id' => CompanyFirmContext::getCurrentFirmId(),
                'voucher_date' => $validated['voucher_date'],
                'voucher_type' => $validated['voucher_type'],
                'narration' => $validated['narration'] ?? null,
            ], $validated['items']);

            return redirect()->route('accounting.vouchers')->with('success', "✓ Journal Voucher {$voucher->voucher_number} posted successfully (₹".number_format((float) $voucher->total_debit, 2).').');
        } catch (UnbalancedJournalException $e) {
            return back()->withInput()->with('error', '🚫 Disallowed: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Voucher creation failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Failed to create journal voucher: '.$e->getMessage());
        }
    }

    /**
     * Ledger statement with running balance for a specific account.
     */
    public function ledger(int $id, Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $statement = $this->accountingService->getLedgerStatement($id, $fromDate, $toDate);

        return view('accounting.ledger', [
            'account' => $statement['account'],
            'openingBalance' => $statement['opening_balance'],
            'transactions' => $statement['transactions'],
            'totalDebit' => $statement['total_debit'],
            'totalCredit' => $statement['total_credit'],
            'closingBalance' => $statement['closing_balance'],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    /**
     * Trial Balance view verifying that Grand Debit equals Grand Credit.
     */
    public function trialBalance(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $trialBalance = $this->accountingService->getTrialBalance($firmId);

        return view('accounting.trial-balance', compact('trialBalance'));
    }
}
