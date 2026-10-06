<?php

namespace App\Services;

use App\Exceptions\UnbalancedJournalException;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\PaymentReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    /**
     * Creates a double-entry journal voucher enforcing the Debit == Credit invariant.
     *
     * @param  array{
     *     firm_id?: int|null,
     *     voucher_number?: string|null,
     *     voucher_date: string|Carbon,
     *     voucher_type?: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     narration?: string|null
     * }  $data
     * @param  array<int, array{
     *     account_id?: int,
     *     account_code?: string,
     *     debit_amount?: float|numeric,
     *     credit_amount?: float|numeric,
     *     remarks?: string|null
     * }>  $items
     *
     * @throws UnbalancedJournalException
     */
    public function createJournalVoucher(array $data, array $items): JournalVoucher
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $preparedItems = [];

        foreach ($items as $item) {
            $accountId = $item['account_id'] ?? null;
            if (! $accountId && ! empty($item['account_code'])) {
                $account = ChartOfAccount::where('account_code', $item['account_code'])->first();
                $accountId = $account?->id;
            }

            if (! $accountId) {
                throw new \InvalidArgumentException('Every voucher item must have a valid account_id or account_code.');
            }

            $debit = round((float) ($item['debit_amount'] ?? 0.0), 2);
            $credit = round((float) ($item['credit_amount'] ?? 0.0), 2);

            if ($debit <= 0.0 && $credit <= 0.0) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $preparedItems[] = [
                'account_id' => $accountId,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        // Double-entry invariant check: Sum(Debit) == Sum(Credit)
        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw new UnbalancedJournalException($totalDebit, $totalCredit);
        }

        $voucherDate = $data['voucher_date'] instanceof Carbon
            ? $data['voucher_date']
            : Carbon::parse($data['voucher_date']);

        $firmId = $data['firm_id'] ?? CompanyFirmContext::getCurrentFirmId();

        $voucherNumber = $data['voucher_number'] ?? $this->generateVoucherNumber($data['voucher_type'] ?? 'JOURNAL', $firmId, $voucherDate);

        return DB::transaction(function () use ($data, $preparedItems, $totalDebit, $totalCredit, $voucherDate, $firmId, $voucherNumber) {
            $voucher = JournalVoucher::create([
                'firm_id' => $firmId,
                'voucher_number' => $voucherNumber,
                'voucher_date' => $voucherDate,
                'voucher_type' => strtoupper($data['voucher_type'] ?? 'JOURNAL'),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'narration' => $data['narration'] ?? null,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            foreach ($preparedItems as $pItem) {
                $voucher->items()->create($pItem);

                // Update real-time balance on the ChartOfAccount ledger
                $account = ChartOfAccount::lockForUpdate()->find($pItem['account_id']);
                if ($account) {
                    $debit = (float) $pItem['debit_amount'];
                    $credit = (float) $pItem['credit_amount'];

                    if ($account->isDebitNormal()) {
                        // Assets & Expenses: Debit increases, Credit decreases
                        $account->current_balance = round((float) $account->current_balance + $debit - $credit, 2);
                    } else {
                        // Liabilities, Equity & Income: Credit increases, Debit decreases
                        $account->current_balance = round((float) $account->current_balance + $credit - $debit, 2);
                    }
                    $account->save();
                }
            }

            return $voucher->load('items.account');
        });
    }

    /**
     * Automatically posts double-entry accounting entries for a finalized Tax Invoice.
     * Dr. Sundry Debtors (Total Invoice Value)
     *   Cr. Sales Revenue (Taxable Material Amount)
     *   Cr. Freight Revenue / Expenses (Freight Amount, if > 0)
     *   Cr. Output CGST (if > 0)
     *   Cr. Output SGST (if > 0)
     *   Cr. Output IGST (if > 0)
     *   Cr. TCS Payable (if > 0)
     */
    public function postFromInvoice(Invoice $invoice): JournalVoucher
    {
        // Check if voucher already exists for this invoice
        $existing = JournalVoucher::where('reference_type', 'INVOICE')
            ->where('reference_id', $invoice->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $debtorsAccount = $this->getOrCreateAccount('1030', 'Sundry Debtors (Receivables)', 'ASSETS', 'SUNDRY_DEBTORS');
        $salesAccount = $this->getOrCreateAccount('4010', 'Sales Revenue', 'INCOME', 'SALES');
        $outputCgst = $this->getOrCreateAccount('2021', 'Output CGST Ledger', 'LIABILITIES', 'TAX_OUTPUT');
        $outputSgst = $this->getOrCreateAccount('2022', 'Output SGST Ledger', 'LIABILITIES', 'TAX_OUTPUT');
        $outputIgst = $this->getOrCreateAccount('2023', 'Output IGST Ledger', 'LIABILITIES', 'TAX_OUTPUT');
        $tcsAccount = $this->getOrCreateAccount('2030', 'TCS Payable (206C 1H)', 'LIABILITIES', 'TAX_OUTPUT');

        $totalAmount = round((float) $invoice->total_amount, 2);
        $taxableAmount = round((float) ($invoice->taxable_amount ?? ($invoice->material_subtotal + $invoice->freight_amount)), 2);
        $cgst = round((float) ($invoice->cgst_amount ?? 0.0), 2);
        $sgst = round((float) ($invoice->sgst_amount ?? 0.0), 2);
        $igst = round((float) ($invoice->igst_amount ?? 0.0), 2);
        $tcs = round((float) ($invoice->tcs_amount ?? 0.0), 2);

        $items = [];

        // 1. Debit Sundry Debtors (Total Amount)
        $items[] = [
            'account_id' => $debtorsAccount->id,
            'debit_amount' => $totalAmount,
            'credit_amount' => 0.00,
            'remarks' => "Receivable from {$invoice->customer?->company_name} for Invoice {$invoice->invoice_number}",
        ];

        // 2. Credit Sales Revenue (Taxable Amount)
        $items[] = [
            'account_id' => $salesAccount->id,
            'debit_amount' => 0.00,
            'credit_amount' => $taxableAmount,
            'remarks' => "Sales revenue for Invoice {$invoice->invoice_number}",
        ];

        // 3. Credit Output Taxes
        if ($cgst > 0) {
            $items[] = [
                'account_id' => $outputCgst->id,
                'debit_amount' => 0.00,
                'credit_amount' => $cgst,
                'remarks' => "CGST on Invoice {$invoice->invoice_number}",
            ];
        }

        if ($sgst > 0) {
            $items[] = [
                'account_id' => $outputSgst->id,
                'debit_amount' => 0.00,
                'credit_amount' => $sgst,
                'remarks' => "SGST on Invoice {$invoice->invoice_number}",
            ];
        }

        if ($igst > 0) {
            $items[] = [
                'account_id' => $outputIgst->id,
                'debit_amount' => 0.00,
                'credit_amount' => $igst,
                'remarks' => "IGST on Invoice {$invoice->invoice_number}",
            ];
        }

        // 4. Credit TCS Payable
        if ($tcs > 0) {
            $items[] = [
                'account_id' => $tcsAccount->id,
                'debit_amount' => 0.00,
                'credit_amount' => $tcs,
                'remarks' => "TCS 206C(1H) on Invoice {$invoice->invoice_number}",
            ];
        }

        return $this->createJournalVoucher([
            'firm_id' => $invoice->firm_id,
            'voucher_type' => 'SALES',
            'reference_type' => 'INVOICE',
            'reference_id' => $invoice->id,
            'voucher_date' => $invoice->invoice_date,
            'narration' => "Sales posting for Invoice #{$invoice->invoice_number} to {$invoice->customer?->company_name}",
        ], $items);
    }

    /**
     * Automatically posts double-entry accounting entries for a Payment Receipt.
     * Dr. Bank / Cash (Amount Received)
     *   Cr. Sundry Debtors (Receivable reduction)
     */
    public function postFromReceipt(PaymentReceipt $receipt, string $bankOrCash = 'BANK'): JournalVoucher
    {
        $existing = JournalVoucher::where('reference_type', 'PAYMENT')
            ->where('reference_id', $receipt->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $isCash = strtoupper($receipt->payment_mode) === 'CASH' || strtoupper($bankOrCash) === 'CASH';
        $paymentAccount = $isCash
            ? $this->getOrCreateAccount('1010', 'Cash Account', 'ASSETS', 'CASH')
            : $this->getOrCreateAccount('1020', 'Bank Account (Current)', 'ASSETS', 'BANK');

        $debtorsAccount = $this->getOrCreateAccount('1030', 'Sundry Debtors (Receivables)', 'ASSETS', 'SUNDRY_DEBTORS');

        $amount = round((float) $receipt->amount_received, 2);

        $items = [
            [
                'account_id' => $paymentAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0.00,
                'remarks' => "Receipt #{$receipt->receipt_number} via {$receipt->payment_mode}",
            ],
            [
                'account_id' => $debtorsAccount->id,
                'debit_amount' => 0.00,
                'credit_amount' => $amount,
                'remarks' => "Payment from {$receipt->customer?->company_name} for Invoice {$receipt->invoice?->invoice_number}",
            ],
        ];

        return $this->createJournalVoucher([
            'firm_id' => $receipt->invoice?->firm_id,
            'voucher_type' => 'RECEIPT',
            'reference_type' => 'PAYMENT',
            'reference_id' => $receipt->id,
            'voucher_date' => $receipt->receipt_date,
            'narration' => "Payment receipt #{$receipt->receipt_number} from {$receipt->customer?->company_name}",
        ], $items);
    }

    /**
     * Automatically posts double-entry accounting entries for an Expense.
     * With ITC:
     *   Dr. Expense Category Account (Base Amount)
     *   Dr. Input CGST (if eligible and > 0)
     *   Dr. Input SGST (if eligible and > 0)
     *   Dr. Input IGST (if eligible and > 0)
     *   Cr. Bank / Cash (if PAID) OR Sundry Creditors (if PENDING)
     * Ineligible ITC:
     *   Dr. Expense Category Account (Total Amount including non-claimable GST)
     *   Cr. Bank / Cash / Sundry Creditors
     */
    public function postFromExpense(Expense $expense, string $paymentMode = 'BANK'): JournalVoucher
    {
        $existing = JournalVoucher::where('reference_type', 'EXPENSE')
            ->where('reference_id', $expense->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Determine expense ledger based on category
        $expenseAccount = $this->resolveExpenseAccount($expense->expense_category);

        $baseAmount = round((float) $expense->base_amount, 2);
        $totalAmount = round((float) $expense->total_amount, 2);
        $cgst = round((float) $expense->cgst_amount, 2);
        $sgst = round((float) $expense->sgst_amount, 2);
        $igst = round((float) $expense->igst_amount, 2);

        $items = [];

        if ($expense->is_itc_eligible) {
            // ITC is claimable: split base expense and tax inputs
            $items[] = [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $baseAmount,
                'credit_amount' => 0.00,
                'remarks' => "{$expense->expense_category} - Invoice #{$expense->vendor_invoice_number}",
            ];

            if ($cgst > 0) {
                $inputCgst = $this->getOrCreateAccount('1041', 'Input CGST Ledger', 'ASSETS', 'TAX_INPUT');
                $items[] = [
                    'account_id' => $inputCgst->id,
                    'debit_amount' => $cgst,
                    'credit_amount' => 0.00,
                    'remarks' => 'Input CGST (ITC Eligible)',
                ];
            }

            if ($sgst > 0) {
                $inputSgst = $this->getOrCreateAccount('1042', 'Input SGST Ledger', 'ASSETS', 'TAX_INPUT');
                $items[] = [
                    'account_id' => $inputSgst->id,
                    'debit_amount' => $sgst,
                    'credit_amount' => 0.00,
                    'remarks' => 'Input SGST (ITC Eligible)',
                ];
            }

            if ($igst > 0) {
                $inputIgst = $this->getOrCreateAccount('1043', 'Input IGST Ledger', 'ASSETS', 'TAX_INPUT');
                $items[] = [
                    'account_id' => $inputIgst->id,
                    'debit_amount' => $igst,
                    'credit_amount' => 0.00,
                    'remarks' => 'Input IGST (ITC Eligible)',
                ];
            }
        } else {
            // Ineligible ITC: full total amount debited to expense (capitalized tax)
            $items[] = [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $totalAmount,
                'credit_amount' => 0.00,
                'remarks' => "{$expense->expense_category} (ITC Ineligible) - Total expense",
            ];
        }

        // Credit settlement: Cash / Bank if PAID, or Sundry Creditors if PENDING
        if (strtoupper($expense->payment_status) === 'PAID') {
            $isCash = strtoupper($paymentMode) === 'CASH';
            $creditAccount = $isCash
                ? $this->getOrCreateAccount('1010', 'Cash Account', 'ASSETS', 'CASH')
                : $this->getOrCreateAccount('1020', 'Bank Account (Current)', 'ASSETS', 'BANK');
        } else {
            $creditAccount = $this->getOrCreateAccount('2010', 'Sundry Creditors (Payables)', 'LIABILITIES', 'SUNDRY_CREDITORS');
        }

        $items[] = [
            'account_id' => $creditAccount->id,
            'debit_amount' => 0.00,
            'credit_amount' => $totalAmount,
            'remarks' => "Payment/Payable for Expense #{$expense->id}",
        ];

        return $this->createJournalVoucher([
            'firm_id' => $expense->firm_id,
            'voucher_type' => 'PURCHASE',
            'reference_type' => 'EXPENSE',
            'reference_id' => $expense->id,
            'voucher_date' => $expense->expense_date,
            'narration' => "Expense booking: {$expense->expense_category} (Vendor: {$expense->vendor?->company_name})",
        ], $items);
    }

    /**
     * Produces a running ledger statement for a given account.
     *
     * @return array{
     *     account: ChartOfAccount,
     *     opening_balance: float,
     *     transactions: array<int, array{
     *         id: int,
     *         date: string,
     *         voucher_number: string,
     *         voucher_type: string,
     *         narration: string,
     *         remarks: string|null,
     *         debit: float,
     *         credit: float,
     *         balance: float
     *     }>,
     *     total_debit: float,
     *     total_credit: float,
     *     closing_balance: float
     * }
     */
    public function getLedgerStatement(int $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $account = ChartOfAccount::findOrFail($accountId);
        $isDebitNormal = $account->isDebitNormal();

        $query = JournalVoucherItem::with('voucher')
            ->where('account_id', $accountId)
            ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
            ->select('journal_voucher_items.*', 'journal_vouchers.voucher_date', 'journal_vouchers.voucher_number', 'journal_vouchers.voucher_type', 'journal_vouchers.narration')
            ->orderBy('journal_vouchers.voucher_date', 'asc')
            ->orderBy('journal_vouchers.id', 'asc');

        $openingBalance = (float) $account->opening_balance;

        // If fromDate is set, compute opening balance prior to fromDate
        if ($fromDate) {
            $priorQuery = JournalVoucherItem::where('account_id', $accountId)
                ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
                ->where('journal_vouchers.voucher_date', '<', $fromDate);

            $priorDebit = (float) $priorQuery->sum('journal_voucher_items.debit_amount');
            $priorCredit = (float) $priorQuery->sum('journal_voucher_items.credit_amount');

            if ($isDebitNormal) {
                $openingBalance = round($openingBalance + $priorDebit - $priorCredit, 2);
            } else {
                $openingBalance = round($openingBalance + $priorCredit - $priorDebit, 2);
            }

            $query->where('journal_vouchers.voucher_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_vouchers.voucher_date', '<=', $toDate);
        }

        $items = $query->get();

        $runningBalance = $openingBalance;
        $transactions = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $debit = (float) $item->debit_amount;
            $credit = (float) $item->credit_amount;

            $totalDebit += $debit;
            $totalCredit += $credit;

            if ($isDebitNormal) {
                $runningBalance = round($runningBalance + $debit - $credit, 2);
            } else {
                $runningBalance = round($runningBalance + $credit - $debit, 2);
            }

            $transactions[] = [
                'id' => $item->id,
                'date' => Carbon::parse($item->voucher_date)->format('d M Y'),
                'voucher_number' => $item->voucher_number,
                'voucher_type' => $item->voucher_type,
                'narration' => $item->narration ?? '',
                'remarks' => $item->remarks,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => $runningBalance,
        ];
    }

    /**
     * Generates Trial Balance data across all chart of accounts.
     *
     * @return array{
     *     groups: array<string, array{
     *         accounts: array<int, array{
     *             id: int,
     *             code: string,
     *             name: string,
     *             debit: float,
     *             credit: float
     *         }>,
     *         total_debit: float,
     *         total_credit: float
     *     }>,
     *     grand_debit: float,
     *     grand_credit: float,
     *     is_balanced: bool
     * }
     */
    public function getTrialBalance(?int $firmId = null): array
    {
        $accounts = ChartOfAccount::active()->orderBy('account_code')->get();

        $groups = [
            'ASSETS' => ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0],
            'LIABILITIES' => ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0],
            'EQUITY' => ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0],
            'INCOME' => ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0],
            'EXPENSES' => ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0],
        ];

        $grandDebit = 0.0;
        $grandCredit = 0.0;

        foreach ($accounts as $acc) {
            $group = strtoupper($acc->account_group);
            if (! isset($groups[$group])) {
                $groups[$group] = ['accounts' => [], 'total_debit' => 0.0, 'total_credit' => 0.0];
            }

            $balance = (float) $acc->current_balance;
            $debit = 0.0;
            $credit = 0.0;

            if ($acc->isDebitNormal()) {
                if ($balance >= 0) {
                    $debit = $balance;
                } else {
                    $credit = abs($balance);
                }
            } else {
                if ($balance >= 0) {
                    $credit = $balance;
                } else {
                    $debit = abs($balance);
                }
            }

            $groups[$group]['accounts'][] = [
                'id' => $acc->id,
                'code' => $acc->account_code,
                'name' => $acc->account_name,
                'sub_group' => $acc->sub_group,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $groups[$group]['total_debit'] += $debit;
            $groups[$group]['total_credit'] += $credit;

            $grandDebit += $debit;
            $grandCredit += $credit;
        }

        $grandDebit = round($grandDebit, 2);
        $grandCredit = round($grandCredit, 2);

        return [
            'groups' => $groups,
            'grand_debit' => $grandDebit,
            'grand_credit' => $grandCredit,
            'is_balanced' => abs($grandDebit - $grandCredit) < 0.01,
        ];
    }

    /**
     * Resolves or creates a system fallback account.
     */
    public function getOrCreateAccount(string $code, string $name, string $group, string $subGroup): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate([
            'account_code' => $code,
        ], [
            'account_name' => $name,
            'account_group' => $group,
            'sub_group' => $subGroup,
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Resolves expense category to appropriate ledger account.
     */
    protected function resolveExpenseAccount(string $category): ChartOfAccount
    {
        $cat = strtoupper(trim($category));

        if (str_contains($cat, 'FREIGHT') || str_contains($cat, 'CARTAGE') || str_contains($cat, 'TRANSPORT')) {
            return $this->getOrCreateAccount('5020', 'Freight Inward / Cartage', 'EXPENSES', 'DIRECT_EXPENSES');
        }

        if (str_contains($cat, 'PURCHASE') || str_contains($cat, 'RAW MATERIAL') || str_contains($cat, 'PACKING')) {
            return $this->getOrCreateAccount('5010', 'Purchase Account', 'EXPENSES', 'DIRECT_EXPENSES');
        }

        if (str_contains($cat, 'DEPRECIATION')) {
            return $this->getOrCreateAccount('5030', 'Depreciation Expense', 'EXPENSES', 'INDIRECT_EXPENSES');
        }

        return $this->getOrCreateAccount('5040', 'General & Administrative Expenses', 'EXPENSES', 'INDIRECT_EXPENSES');
    }

    /**
     * Generates a sequential voucher number.
     */
    protected function generateVoucherNumber(string $voucherType, ?int $firmId, Carbon $date): string
    {
        $prefix = match (strtoupper($voucherType)) {
            'SALES' => 'SV',
            'PURCHASE' => 'PV',
            'PAYMENT' => 'PMT',
            'RECEIPT' => 'RCT',
            'CONTRA' => 'CNTR',
            'DEPRECIATION' => 'DEP',
            default => 'JV',
        };

        $yearStr = $date->format('y').'-'.($date->copy()->addYear()->format('y'));
        $dateCode = $date->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}/{$yearStr}/{$dateCode}-{$random}";
    }
}
