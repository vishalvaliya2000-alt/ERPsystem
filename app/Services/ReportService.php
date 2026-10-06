<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductionBatch;
use App\Models\Sample;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Generates statutory Balance Sheet with drill-down account balances.
     *
     * @return array<string, mixed>
     */
    public function getBalanceSheet(?int $firmId = null, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : Carbon::today()->endOfDay();

        $query = ChartOfAccount::query();
        if ($firmId) {
            $query->where(function ($q) use ($firmId) {
                $q->where('firm_id', $firmId)->orWhereNull('firm_id');
            });
        }

        $accounts = $query->orderBy('account_code')->get();

        // 1. Current Assets
        $bankAndCash = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && in_array(substr($a->account_code, 0, 3), ['101', '102']));
        $sundryDebtors = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 3) === '103');
        $gstInputItc = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 3) === '104');
        $inventoryStock = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 3) === '105');
        $otherCurrentAssets = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 2) === '10' && ! in_array(substr($a->account_code, 0, 3), ['101', '102', '103', '104', '105']));

        $currentAssetsTotal = (float) ($bankAndCash->sum('current_balance') + $sundryDebtors->sum('current_balance') + $gstInputItc->sum('current_balance') + $inventoryStock->sum('current_balance') + $otherCurrentAssets->sum('current_balance'));

        // 2. Fixed Assets / Non-Current
        $tangibleFixedAssets = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && in_array(substr($a->account_code, 0, 3), ['151', '152', '153', '154']));
        $accumulatedDepreciation = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 3) === '159');
        $otherNonCurrentAssets = $accounts->filter(fn ($a) => $a->account_group === 'ASSETS' && substr($a->account_code, 0, 2) === '15' && ! in_array(substr($a->account_code, 0, 3), ['151', '152', '153', '154', '159']));

        $grossFixedAssets = (float) $tangibleFixedAssets->sum('current_balance');
        $totalDepreciation = (float) abs($accumulatedDepreciation->sum('current_balance'));
        $netFixedAssets = $grossFixedAssets - $totalDepreciation + (float) $otherNonCurrentAssets->sum('current_balance');

        $totalAssets = $currentAssetsTotal + $netFixedAssets;

        // 3. Current Liabilities
        $sundryCreditors = $accounts->filter(fn ($a) => $a->account_group === 'LIABILITIES' && substr($a->account_code, 0, 3) === '201');
        $outputGstPayable = $accounts->filter(fn ($a) => $a->account_group === 'LIABILITIES' && substr($a->account_code, 0, 3) === '202');
        $tcsTdsPayable = $accounts->filter(fn ($a) => $a->account_group === 'LIABILITIES' && in_array(substr($a->account_code, 0, 3), ['203', '204']));
        $otherCurrentLiabilities = $accounts->filter(fn ($a) => $a->account_group === 'LIABILITIES' && substr($a->account_code, 0, 2) === '20' && ! in_array(substr($a->account_code, 0, 3), ['201', '202', '203', '204']));

        $currentLiabilitiesTotal = (float) ($sundryCreditors->sum('current_balance') + $outputGstPayable->sum('current_balance') + $tcsTdsPayable->sum('current_balance') + $otherCurrentLiabilities->sum('current_balance'));

        // 4. Non-Current Liabilities
        $longTermDebt = $accounts->filter(fn ($a) => $a->account_group === 'LIABILITIES' && substr($a->account_code, 0, 2) === '25');
        $nonCurrentLiabilitiesTotal = (float) $longTermDebt->sum('current_balance');

        $totalLiabilities = $currentLiabilitiesTotal + $nonCurrentLiabilitiesTotal;

        // 5. Equity & Reserves
        $shareCapital = $accounts->filter(fn ($a) => $a->account_group === 'EQUITY' && substr($a->account_code, 0, 3) === '301');
        $reservesAndSurplus = $accounts->filter(fn ($a) => $a->account_group === 'EQUITY' && substr($a->account_code, 0, 3) === '302');
        $otherEquity = $accounts->filter(fn ($a) => $a->account_group === 'EQUITY' && ! in_array(substr($a->account_code, 0, 3), ['301', '302']));

        // Period Net Profit (Income - Expenses)
        $totalIncome = (float) $accounts->where('account_group', 'INCOME')->sum('current_balance');
        $totalExpenses = (float) $accounts->where('account_group', 'EXPENSES')->sum('current_balance');
        $netProfitOrLoss = $totalIncome - $totalExpenses;

        $totalEquity = (float) ($shareCapital->sum('current_balance') + $reservesAndSurplus->sum('current_balance') + $otherEquity->sum('current_balance') + $netProfitOrLoss);

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $difference = round($totalAssets - $totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOf,
            'assets' => [
                'current' => [
                    'bank_and_cash' => $bankAndCash,
                    'bank_and_cash_total' => (float) $bankAndCash->sum('current_balance'),
                    'sundry_debtors' => $sundryDebtors,
                    'sundry_debtors_total' => (float) $sundryDebtors->sum('current_balance'),
                    'gst_input_itc' => $gstInputItc,
                    'gst_input_itc_total' => (float) $gstInputItc->sum('current_balance'),
                    'inventory' => $inventoryStock,
                    'inventory_total' => (float) $inventoryStock->sum('current_balance'),
                    'other_current' => $otherCurrentAssets,
                    'other_current_total' => (float) $otherCurrentAssets->sum('current_balance'),
                    'total' => $currentAssetsTotal,
                ],
                'fixed' => [
                    'tangible' => $tangibleFixedAssets,
                    'tangible_total' => $grossFixedAssets,
                    'accumulated_depreciation' => $accumulatedDepreciation,
                    'accumulated_depreciation_total' => $totalDepreciation,
                    'other' => $otherNonCurrentAssets,
                    'other_total' => (float) $otherNonCurrentAssets->sum('current_balance'),
                    'net_total' => $netFixedAssets,
                ],
                'grand_total' => $totalAssets,
            ],
            'liabilities' => [
                'current' => [
                    'sundry_creditors' => $sundryCreditors,
                    'sundry_creditors_total' => (float) $sundryCreditors->sum('current_balance'),
                    'output_gst' => $outputGstPayable,
                    'output_gst_total' => (float) $outputGstPayable->sum('current_balance'),
                    'tcs_tds' => $tcsTdsPayable,
                    'tcs_tds_total' => (float) $tcsTdsPayable->sum('current_balance'),
                    'other_current' => $otherCurrentLiabilities,
                    'other_current_total' => (float) $otherCurrentLiabilities->sum('current_balance'),
                    'total' => $currentLiabilitiesTotal,
                ],
                'non_current' => [
                    'long_term_debt' => $longTermDebt,
                    'total' => $nonCurrentLiabilitiesTotal,
                ],
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'share_capital' => $shareCapital,
                'share_capital_total' => (float) $shareCapital->sum('current_balance'),
                'reserves' => $reservesAndSurplus,
                'reserves_total' => (float) $reservesAndSurplus->sum('current_balance'),
                'net_profit' => $netProfitOrLoss,
                'total' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    /**
     * Bill-wise profit and margin analysis report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getBillProfitReport(array $filters = []): array
    {
        $query = Invoice::with(['customer', 'commercialShipment.ewayBill', 'salesOrder'])
            ->whereNotIn('status', ['CANCELLED', 'DRAFT']);

        if (! empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['firm_id'])) {
            $query->where('firm_id', $filters['firm_id']);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->get();

        $rows = $invoices->map(function ($inv) {
            $revenue = (float) ($inv->subtotal > 0 ? $inv->subtotal : $inv->total_amount);
            $cost = (float) $inv->total_cost_amount;
            $profit = (float) $inv->gross_profit_amount;
            $margin = (float) $inv->gross_profit_percentage;

            if ($profit == 0 && $cost > 0 && $revenue > 0) {
                $profit = $revenue - $cost;
                $margin = round(($profit / $revenue) * 100, 2);
            }

            $health = match (true) {
                $margin >= 20.0 => 'HIGH',
                $margin >= 10.0 => 'HEALTHY',
                $margin >= 0.0 => 'LOW',
                default => 'NEGATIVE',
            };

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date,
                'customer_name' => $inv->customer?->company_name ?? 'Client',
                'customer_id' => $inv->customer_id,
                'lr_number' => $inv->commercialShipment?->lr_number ?? $inv->effective_shipment?->lr_number ?? '—',
                'eway_bill_number' => $inv->commercialShipment?->ewayBill?->eway_bill_number,
                'total_amount' => (float) $inv->total_amount,
                'revenue' => $revenue,
                'cost' => $cost,
                'gross_profit' => $profit,
                'margin_percentage' => $margin,
                'health' => $health,
                'status' => $inv->status,
            ];
        });

        if (isset($filters['min_margin']) && $filters['min_margin'] !== '') {
            $min = (float) $filters['min_margin'];
            $rows = $rows->filter(fn ($r) => $r['margin_percentage'] >= $min)->values();
        }

        $totalRevenue = (float) $rows->sum('revenue');
        $totalCost = (float) $rows->sum('cost');
        $totalProfit = (float) $rows->sum('gross_profit');
        $avgMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0.0;

        return [
            'rows' => $rows,
            'summary' => [
                'total_invoices' => $rows->count(),
                'total_billed' => (float) $rows->sum('total_amount'),
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'avg_margin' => $avgMargin,
                'high_margin_count' => $rows->where('health', 'HIGH')->count(),
                'healthy_margin_count' => $rows->where('health', 'HEALTHY')->count(),
                'low_margin_count' => $rows->where('health', 'LOW')->count(),
                'negative_margin_count' => $rows->where('health', 'NEGATIVE')->count(),
            ],
        ];
    }

    /**
     * Party-wise customer profitability ranking report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPartyProfitReport(array $filters = []): array
    {
        $billReport = $this->getBillProfitReport($filters);
        $invoiceRows = collect($billReport['rows']);

        $customerIds = $invoiceRows->pluck('customer_id')->unique()->filter()->values();
        $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        $totalCompanyProfit = max(1.0, (float) $invoiceRows->sum('gross_profit'));

        $partyGroups = $invoiceRows->groupBy('customer_id')->map(function ($group, $custId) use ($customers, $totalCompanyProfit) {
            $customer = $customers->get($custId);
            $revenue = (float) $group->sum('revenue');
            $cost = (float) $group->sum('cost');
            $profit = (float) $group->sum('gross_profit');
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;
            $contribution = round(($profit / $totalCompanyProfit) * 100, 2);

            return [
                'customer_id' => $custId,
                'customer_code' => $customer?->customer_code ?? 'CUST-'.$custId,
                'company_name' => $customer?->company_name ?? 'Client Enterprise',
                'state' => $customer?->state ?? 'Domestic',
                'gst_number' => $customer?->gst_number ?? '—',
                'invoice_count' => $group->count(),
                'total_billed' => (float) $group->sum('total_amount'),
                'total_revenue' => $revenue,
                'total_cost' => $cost,
                'gross_profit' => $profit,
                'margin_percentage' => $margin,
                'profit_contribution' => $contribution,
                'total_outstanding' => (float) ($customer?->outstanding_amount ?? 0.0),
            ];
        })->values();

        $sortBy = $filters['sort_by'] ?? 'profit_desc';
        $sortedParties = match ($sortBy) {
            'profit_asc' => $partyGroups->sortBy('gross_profit')->values(),
            'revenue_desc' => $partyGroups->sortByDesc('total_revenue')->values(),
            'margin_desc' => $partyGroups->sortByDesc('margin_percentage')->values(),
            default => $partyGroups->sortByDesc('gross_profit')->values(),
        };

        $totalRevenue = (float) $sortedParties->sum('total_revenue');
        $totalProfit = (float) $sortedParties->sum('gross_profit');

        return [
            'parties' => $sortedParties,
            'summary' => [
                'total_parties' => $sortedParties->count(),
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'top_party' => $sortedParties->first(),
                'avg_party_profit' => $sortedParties->count() > 0 ? round($totalProfit / $sortedParties->count(), 2) : 0.0,
            ],
        ];
    }

    /**
     * Accounts Receivable (AR) Aging analysis report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getArAgingReport(array $filters = []): array
    {
        $asOf = ! empty($filters['as_of_date']) ? Carbon::parse($filters['as_of_date'])->startOfDay() : Carbon::today();

        $query = Invoice::with(['customer', 'commercialShipment'])
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['PAID', 'CANCELLED', 'DRAFT'])
            ->whereNotNull('due_date');

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['firm_id'])) {
            $query->where('firm_id', $filters['firm_id']);
        }

        $invoices = $query->orderBy('due_date', 'asc')->get();

        $rows = $invoices->map(function ($inv) use ($asOf) {
            $dueDate = Carbon::parse($inv->due_date)->startOfDay();
            $daysOverdue = $asOf->greaterThan($dueDate) ? (int) $dueDate->diffInDays($asOf) : 0;
            $balance = (float) $inv->balance_due;

            $bucket = match (true) {
                $daysOverdue <= 0 => 'CURRENT',
                $daysOverdue <= 30 => 'DAYS_0_30',
                $daysOverdue <= 60 => 'DAYS_31_60',
                $daysOverdue <= 90 => 'DAYS_61_90',
                default => 'DAYS_90_PLUS',
            };

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $dueDate,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer?->company_name ?? 'Client',
                'customer_phone' => $inv->customer?->primary_phone ?: $inv->customer?->phone,
                'total_amount' => (float) $inv->total_amount,
                'amount_received' => (float) $inv->amount_received,
                'balance_due' => $balance,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'is_overdue' => $daysOverdue > 0,
            ];
        });

        // Customer grouped aggregation
        $customerGroups = $rows->groupBy('customer_id')->map(function ($items, $custId) {
            $first = $items->first();

            return [
                'customer_id' => $custId,
                'customer_name' => $first['customer_name'],
                'customer_phone' => $first['customer_phone'],
                'total_balance' => (float) $items->sum('balance_due'),
                'current' => (float) $items->where('bucket', 'CURRENT')->sum('balance_due'),
                'days_0_30' => (float) $items->where('bucket', 'DAYS_0_30')->sum('balance_due'),
                'days_31_60' => (float) $items->where('bucket', 'DAYS_31_60')->sum('balance_due'),
                'days_61_90' => (float) $items->where('bucket', 'DAYS_61_90')->sum('balance_due'),
                'days_90_plus' => (float) $items->where('bucket', 'DAYS_90_PLUS')->sum('balance_due'),
                'invoices_count' => $items->count(),
                'max_overdue_days' => $items->max('days_overdue'),
                'invoices' => $items->values(),
            ];
        })->values();

        $totalBalance = (float) $rows->sum('balance_due');
        $totalOverdue = (float) $rows->where('is_overdue', true)->sum('balance_due');
        $critical90Plus = (float) $rows->where('bucket', 'DAYS_90_PLUS')->sum('balance_due');

        return [
            'as_of_date' => $asOf,
            'customers' => $customerGroups,
            'invoices' => $rows,
            'summary' => [
                'total_outstanding' => $totalBalance,
                'total_overdue' => $totalOverdue,
                'current_total' => (float) $rows->where('bucket', 'CURRENT')->sum('balance_due'),
                'days_0_30_total' => (float) $rows->where('bucket', 'DAYS_0_30')->sum('balance_due'),
                'days_31_60_total' => (float) $rows->where('bucket', 'DAYS_31_60')->sum('balance_due'),
                'days_61_90_total' => (float) $rows->where('bucket', 'DAYS_61_90')->sum('balance_due'),
                'days_90_plus_total' => $critical90Plus,
                'invoices_count' => $rows->count(),
            ],
        ];
    }

    /**
     * Batch & Serial trace report from Raw Material -> Production Batch -> Sample Trials -> Order -> Shipment -> Invoice.
     *
     * @return array<string, mixed>
     */
    public function getBatchTraceReport(?string $searchTerm = null): array
    {
        $batchQuery = ProductionBatch::with(['product']);

        if (! empty($searchTerm)) {
            $term = trim($searchTerm);
            $batchQuery->where(function ($q) use ($term) {
                $q->where('batch_number', 'like', "%{$term}%")
                    ->orWhere('raw_lot_number', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('product_name', 'like', "%{$term}%"));
            });
        }

        $batches = $batchQuery->orderBy('production_date', 'desc')->take(20)->get();

        $traceTree = $batches->map(function ($batch) {
            // 1. Linked QC / Sample trials
            $samples = Sample::with('customer')
                ->where('batch_number', $batch->batch_number)
                ->orWhere('product_id', $batch->product_id)
                ->take(5)
                ->get();

            // 2. Linked Dispatches / Shipments
            $shipments = CommercialShipment::with(['salesOrder.customer', 'ewayBill', 'items.salesOrderItem'])
                ->whereHas('items', function ($iq) use ($batch) {
                    $iq->where('batch_number', $batch->batch_number)
                        ->orWhereHas('salesOrderItem', fn ($soq) => $soq->where('product_id', $batch->product_id));
                })
                ->take(5)
                ->get();

            // 3. Linked Invoices
            $shipmentIds = $shipments->pluck('id')->filter();
            $invoices = Invoice::with(['customer', 'commercialShipment.ewayBill'])
                ->where(function ($iq) use ($shipmentIds, $batch) {
                    $iq->whereIn('commercial_shipment_id', $shipmentIds)
                        ->orWhereHas('items', fn ($it) => $it->where('product_id', $batch->product_id));
                })
                ->take(5)
                ->get();

            return [
                'batch' => $batch,
                'raw_lot' => $batch->raw_lot_number ?: 'RAW-'.substr($batch->batch_number, -4),
                'product' => $batch->product,
                'samples' => $samples,
                'shipments' => $shipments,
                'invoices' => $invoices,
                'has_full_chain' => $shipments->isNotEmpty() && $invoices->isNotEmpty(),
            ];
        });

        return [
            'search_term' => $searchTerm,
            'batches_count' => $batches->count(),
            'traces' => $traceTree,
        ];
    }
}
