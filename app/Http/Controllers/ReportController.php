<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ArAgingExport;
use App\Exports\BalanceSheetExport;
use App\Exports\BatchTraceExport;
use App\Exports\BillProfitExport;
use App\Exports\PartyProfitExport;
use App\Models\Customer;
use App\Models\Firm;
use App\Models\Invoice;
use App\Models\ProductionBatch;
use App\Services\CompanyFirmContext;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Reports Command Hub dashboard.
     */
    public function index(Request $request)
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $activeFirm = CompanyFirmContext::getFirm();

        // High-level overview KPIs
        $totalReceivables = (float) Invoice::where('balance_due', '>', 0)
            ->whereNotIn('status', ['PAID', 'CANCELLED', 'DRAFT'])
            ->sum('balance_due');

        $monthlyBilled = (float) Invoice::whereDate('invoice_date', '>=', $currentMonth)
            ->whereNotIn('status', ['CANCELLED', 'DRAFT'])
            ->sum('total_amount');

        $monthlyProfit = (float) Invoice::whereDate('invoice_date', '>=', $currentMonth)
            ->whereNotIn('status', ['CANCELLED', 'DRAFT'])
            ->sum('gross_profit_amount');

        $monthlyRevenue = (float) Invoice::whereDate('invoice_date', '>=', $currentMonth)
            ->whereNotIn('status', ['CANCELLED', 'DRAFT'])
            ->sum('subtotal');

        $avgMarginMtd = $monthlyRevenue > 0
            ? round(($monthlyProfit / $monthlyRevenue) * 100, 1)
            : 0.0;

        $batchCount = ProductionBatch::count();

        $stats = [
            'total_receivables' => $totalReceivables,
            'monthly_billed' => $monthlyBilled,
            'monthly_profit' => $monthlyProfit,
            'avg_margin_mtd' => $avgMarginMtd,
            'batch_count' => $batchCount,
        ];

        return view('reports.index', compact('stats', 'activeFirm'));
    }

    /**
     * Statutory Balance Sheet report view.
     */
    public function balanceSheet(Request $request)
    {
        $firmId = $request->filled('firm_id') ? (int) $request->input('firm_id') : null;
        $asOfDate = $request->input('as_of_date', Carbon::today()->format('Y-m-d'));

        $data = $this->reportService->getBalanceSheet($firmId, $asOfDate);
        $firms = Firm::where('is_active', true)->get();
        $activeFirm = $firmId ? Firm::find($firmId) : CompanyFirmContext::getFirm();

        return view('reports.balance-sheet', compact('data', 'firms', 'firmId', 'asOfDate', 'activeFirm'));
    }

    /**
     * Export Balance Sheet as PDF.
     */
    public function balanceSheetPdf(Request $request)
    {
        $firmId = $request->filled('firm_id') ? (int) $request->input('firm_id') : null;
        $asOfDate = $request->input('as_of_date', Carbon::today()->format('Y-m-d'));

        $data = $this->reportService->getBalanceSheet($firmId, $asOfDate);
        $firm = $firmId ? Firm::find($firmId) : CompanyFirmContext::getFirm();

        $pdf = Pdf::loadView('reports.pdf.balance-sheet', compact('data', 'firm', 'asOfDate'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Balance_Sheet_'.Carbon::parse($asOfDate)->format('Ymd').'.pdf');
    }

    /**
     * Export Balance Sheet as Excel.
     */
    public function balanceSheetExcel(Request $request): BinaryFileResponse
    {
        $firmId = $request->filled('firm_id') ? (int) $request->input('firm_id') : null;
        $asOfDate = $request->input('as_of_date', Carbon::today()->format('Y-m-d'));

        $data = $this->reportService->getBalanceSheet($firmId, $asOfDate);

        return Excel::download(
            new BalanceSheetExport($data),
            'Balance_Sheet_'.Carbon::parse($asOfDate)->format('Ymd').'.xlsx'
        );
    }

    /**
     * Bill-wise profit and margin report view.
     */
    public function billProfit(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date', 'customer_id', 'firm_id', 'min_margin']);
        $data = $this->reportService->getBillProfitReport($filters);

        $customers = Customer::orderBy('company_name')->get();
        $firms = Firm::where('is_active', true)->get();
        $activeFirm = CompanyFirmContext::getFirm();

        return view('reports.bill-profit', compact('data', 'customers', 'firms', 'filters', 'activeFirm'));
    }

    /**
     * Export Bill-wise profit report as PDF.
     */
    public function billProfitPdf(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date', 'customer_id', 'firm_id', 'min_margin']);
        $data = $this->reportService->getBillProfitReport($filters);
        $firm = CompanyFirmContext::getFirm();

        $pdf = Pdf::loadView('reports.pdf.bill-profit', compact('data', 'firm', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('Bill_Profit_Report_'.date('Ymd').'.pdf');
    }

    /**
     * Export Bill-wise profit report as Excel.
     */
    public function billProfitExcel(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'customer_id', 'firm_id', 'min_margin']);
        $data = $this->reportService->getBillProfitReport($filters);

        return Excel::download(
            new BillProfitExport($data),
            'Bill_Profit_Report_'.date('Ymd').'.xlsx'
        );
    }

    /**
     * Party-wise customer profitability report view.
     */
    public function partyProfit(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date', 'sort_by']);
        $data = $this->reportService->getPartyProfitReport($filters);
        $activeFirm = CompanyFirmContext::getFirm();

        return view('reports.party-profit', compact('data', 'filters', 'activeFirm'));
    }

    /**
     * Export Party-wise profitability report as PDF.
     */
    public function partyProfitPdf(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date', 'sort_by']);
        $data = $this->reportService->getPartyProfitReport($filters);
        $firm = CompanyFirmContext::getFirm();

        $pdf = Pdf::loadView('reports.pdf.party-profit', compact('data', 'firm', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('Party_Profit_Report_'.date('Ymd').'.pdf');
    }

    /**
     * Export Party-wise profitability report as Excel.
     */
    public function partyProfitExcel(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'sort_by']);
        $data = $this->reportService->getPartyProfitReport($filters);

        return Excel::download(
            new PartyProfitExport($data),
            'Party_Profit_Report_'.date('Ymd').'.xlsx'
        );
    }

    /**
     * Accounts Receivable (AR) Aging analysis view.
     */
    public function arAging(Request $request)
    {
        $filters = $request->only(['as_of_date', 'customer_id', 'firm_id']);
        $data = $this->reportService->getArAgingReport($filters);

        $customers = Customer::orderBy('company_name')->get();
        $firms = Firm::where('is_active', true)->get();
        $activeFirm = CompanyFirmContext::getFirm();

        return view('reports.ar-aging', compact('data', 'customers', 'firms', 'filters', 'activeFirm'));
    }

    /**
     * Export AR Aging report as PDF.
     */
    public function arAgingPdf(Request $request)
    {
        $filters = $request->only(['as_of_date', 'customer_id', 'firm_id']);
        $data = $this->reportService->getArAgingReport($filters);
        $firm = CompanyFirmContext::getFirm();

        $pdf = Pdf::loadView('reports.pdf.ar-aging', compact('data', 'firm', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('AR_Aging_Report_'.date('Ymd').'.pdf');
    }

    /**
     * Export AR Aging report as Excel.
     */
    public function arAgingExcel(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['as_of_date', 'customer_id', 'firm_id']);
        $data = $this->reportService->getArAgingReport($filters);

        return Excel::download(
            new ArAgingExport($data),
            'AR_Aging_Report_'.date('Ymd').'.xlsx'
        );
    }

    /**
     * Multi-stage Batch & Serial Traceability view.
     */
    public function batchTrace(Request $request)
    {
        $searchTerm = $request->input('search');
        $data = $this->reportService->getBatchTraceReport($searchTerm);
        $activeFirm = CompanyFirmContext::getFirm();

        return view('reports.batch-trace', compact('data', 'searchTerm', 'activeFirm'));
    }

    /**
     * Export Batch Traceability report as PDF.
     */
    public function batchTracePdf(Request $request)
    {
        $searchTerm = $request->input('search');
        $data = $this->reportService->getBatchTraceReport($searchTerm);
        $firm = CompanyFirmContext::getFirm();

        $pdf = Pdf::loadView('reports.pdf.batch-trace', compact('data', 'firm', 'searchTerm'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('Batch_Trace_Report_'.date('Ymd').'.pdf');
    }

    /**
     * Export Batch Traceability report as Excel.
     */
    public function batchTraceExcel(Request $request): BinaryFileResponse
    {
        $searchTerm = $request->input('search');
        $data = $this->reportService->getBatchTraceReport($searchTerm);

        return Excel::download(
            new BatchTraceExport($data),
            'Batch_Trace_Report_'.date('Ymd').'.xlsx'
        );
    }
}
