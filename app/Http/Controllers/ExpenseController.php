<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Firm;
use App\Models\Vendor;
use App\Services\AccountingService;
use App\Services\CompanyFirmContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * List expenses with filters and metrics.
     */
    public function index(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $category = $request->query('category');
        $paymentStatus = $request->query('payment_status');
        $itcEligible = $request->query('itc_eligible');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = Expense::with(['vendor', 'firm'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        if ($category) {
            $query->where('expense_category', $category);
        }

        if ($paymentStatus) {
            $query->where('payment_status', strtoupper($paymentStatus));
        }

        if ($itcEligible !== null && $itcEligible !== '') {
            $query->where('is_itc_eligible', (bool) $itcEligible);
        }

        if ($fromDate) {
            $query->where('expense_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('expense_date', '<=', $toDate);
        }

        $expenses = $query->paginate(20)->withQueryString();

        // Metrics for active firm
        $metricsQuery = Expense::query();
        if ($firmId) {
            $metricsQuery->where('firm_id', $firmId);
        }

        $totalBase = (float) (clone $metricsQuery)->sum('base_amount');
        $totalCgst = (float) (clone $metricsQuery)->sum('cgst_amount');
        $totalSgst = (float) (clone $metricsQuery)->sum('sgst_amount');
        $totalIgst = (float) (clone $metricsQuery)->sum('igst_amount');
        $totalGstPaid = round($totalCgst + $totalSgst + $totalIgst, 2);
        $totalExpense = (float) (clone $metricsQuery)->sum('total_amount');
        $eligibleItc = (float) (clone $metricsQuery)->where('is_itc_eligible', true)->sum(DB::raw('cgst_amount + sgst_amount + igst_amount'));
        $pendingPayouts = (float) (clone $metricsQuery)->where('payment_status', 'PENDING')->sum('total_amount');

        $vendors = Vendor::where('is_active', true)->orderBy('company_name')->get();
        $categories = Expense::select('expense_category')->distinct()->pluck('expense_category')->toArray();
        if (empty($categories)) {
            $categories = ['Office Supplies', 'Packaging Material', 'Freight Inward', 'Electricity & Power', 'Repairs & Maintenance', 'Rent', 'Legal & Professional', 'Miscellaneous'];
        }

        return view('expenses.index', compact(
            'expenses',
            'vendors',
            'categories',
            'totalExpense',
            'totalBase',
            'totalGstPaid',
            'eligibleItc',
            'pendingPayouts',
            'category',
            'paymentStatus',
            'itcEligible',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Records a new expense, computes GST split, and posts double-entry journal voucher.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'nullable|exists:vendors,id',
            'expense_category' => 'required|string|max:100',
            'vendor_invoice_number' => 'nullable|string|max:100',
            'expense_date' => 'required|date',
            'base_amount' => 'required|numeric|min:0.01',
            'gst_rate' => 'required|numeric|min:0|max:28',
            'is_itc_eligible' => 'nullable|boolean',
            'itc_type' => 'required|string|in:INPUT_GOODS,INPUT_SERVICES,CAPITAL_GOODS,INELIGIBLE',
            'payment_status' => 'required|string|in:PAID,PENDING',
            'payment_mode' => 'nullable|string|in:BANK,CASH',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $firmId = CompanyFirmContext::getCurrentFirmId();
            $firm = Firm::find($firmId);
            $vendor = ! empty($validated['vendor_id']) ? Vendor::find($validated['vendor_id']) : null;

            $baseAmount = round((float) $validated['base_amount'], 2);
            $gstRate = (float) $validated['gst_rate'];
            $isItcEligible = (bool) ($validated['is_itc_eligible'] ?? ($validated['itc_type'] !== 'INELIGIBLE'));

            // GST Interstate vs Intrastate calculation
            $firmStateCode = $firm ? $firm->resolveStateCode() : '24';
            $vendorState = $vendor ? ($vendor->state ?? 'Gujarat') : 'Gujarat';
            $vendorGstin = $vendor?->gstin;
            $vendorStateCode = $vendorGstin && strlen($vendorGstin) >= 2 ? substr($vendorGstin, 0, 2) : '24';

            $isInterstate = ($vendorStateCode !== $firmStateCode) && ($gstRate > 0);

            $cgst = 0.00;
            $sgst = 0.00;
            $igst = 0.00;

            if ($gstRate > 0) {
                if ($isInterstate) {
                    $igst = round($baseAmount * ($gstRate / 100), 2);
                } else {
                    $halfRate = $gstRate / 2;
                    $cgst = round($baseAmount * ($halfRate / 100), 2);
                    $sgst = round($baseAmount * ($halfRate / 100), 2);
                }
            }

            $totalAmount = round($baseAmount + $cgst + $sgst + $igst, 2);

            $expense = Expense::create([
                'firm_id' => $firmId,
                'vendor_id' => $vendor?->id,
                'expense_category' => $validated['expense_category'],
                'vendor_invoice_number' => $validated['vendor_invoice_number'] ?? null,
                'expense_date' => Carbon::parse($validated['expense_date']),
                'base_amount' => $baseAmount,
                'gst_rate' => $gstRate,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_amount' => $totalAmount,
                'is_itc_eligible' => $isItcEligible,
                'itc_type' => $validated['itc_type'],
                'payment_status' => $validated['payment_status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Auto-post double entry journal voucher
            $paymentMode = $validated['payment_mode'] ?? 'BANK';
            $this->accountingService->postFromExpense($expense, $paymentMode);

            DB::commit();

            return redirect()->route('expenses.index')->with('success', "✓ Expense recorded: {$expense->expense_category} (₹".number_format($totalAmount, 2).') with double-entry voucher.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Expense recording failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Failed to record expense: '.$e->getMessage());
        }
    }

    /**
     * Input Tax Credit (ITC) Register view for GSTR-3B reconciliation.
     */
    public function itcRegister(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $month = $request->query('month', now()->format('Y-m'));

        $query = Expense::with(['vendor', 'firm'])
            ->whereRaw("DATE_FORMAT(expense_date, '%Y-%m') = ?", [$month])
            ->orderBy('expense_date', 'asc');

        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        $expenses = $query->get();

        // Categorize into GSTR-3B Table 4 sections
        $eligibleInputGoods = $expenses->where('is_itc_eligible', true)->where('itc_type', 'INPUT_GOODS');
        $eligibleInputServices = $expenses->where('is_itc_eligible', true)->where('itc_type', 'INPUT_SERVICES');
        $eligibleCapitalGoods = $expenses->where('is_itc_eligible', true)->where('itc_type', 'CAPITAL_GOODS');
        $ineligibleExpenses = $expenses->where('is_itc_eligible', false);

        $summary = [
            'eligible_cgst' => $expenses->where('is_itc_eligible', true)->sum('cgst_amount'),
            'eligible_sgst' => $expenses->where('is_itc_eligible', true)->sum('sgst_amount'),
            'eligible_igst' => $expenses->where('is_itc_eligible', true)->sum('igst_amount'),
            'ineligible_tax' => $ineligibleExpenses->sum(fn ($e) => $e->cgst_amount + $e->sgst_amount + $e->igst_amount),
            'total_available_itc' => $expenses->where('is_itc_eligible', true)->sum(fn ($e) => $e->cgst_amount + $e->sgst_amount + $e->igst_amount),
        ];

        return view('expenses.itc-register', compact(
            'expenses',
            'month',
            'eligibleInputGoods',
            'eligibleInputServices',
            'eligibleCapitalGoods',
            'ineligibleExpenses',
            'summary'
        ));
    }

    /**
     * Deletes an expense.
     */
    public function destroy(int $id)
    {
        $expense = Expense::with('journalVoucher')->findOrFail($id);

        DB::transaction(function () use ($expense) {
            if ($expense->journalVoucher) {
                $expense->journalVoucher->delete();
            }
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', '✓ Expense record deleted successfully.');
    }
}
