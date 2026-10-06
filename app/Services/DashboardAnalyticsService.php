<?php

namespace App\Services;

use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardAnalyticsService
{
    /**
     * Get compiled analytics bundle for initial hydration or API responses
     */
    public function getAllAnalytics(?int $tenantId = null, array $filters = []): array
    {
        $hasTenantCol = Schema::hasColumn('invoices', 'tenant_id');
        $tenantId = $hasTenantCol ? ($tenantId ?: TenantManager::getTenantId()) : null;

        $dateRange = $this->resolveDateRange($filters);

        $kpis = $this->getKpis($tenantId, $dateRange, $filters);
        $yearlySales = $this->getYearlySalesTrend($tenantId, $filters);
        $monthlySales = $this->getMonthlySalesTrend($tenantId, $dateRange, $filters);
        $productMonthlyQty = $this->getProductMonthlySalesQty($tenantId, $dateRange, $filters);
        $topProducts = $this->getTopProductsByRevenue($tenantId, $dateRange, $filters, 5);
        $topCustomers = $this->getTopCustomersBySales($tenantId, $dateRange, $filters, 5);
        $collectionsVsOutstanding = $this->getCollectionsVsOutstanding($tenantId, $dateRange, $filters);
        $pipelineFunnel = $this->getSalesPipelineFunnel($tenantId);
        $receivablesAging = $this->getReceivablesAging($tenantId);
        $operationalStatus = $this->getOperationalStatus($tenantId);
        $filterOptions = $this->getFilterOptions($tenantId);

        return [
            'date_range' => [
                'period' => $filters['period'] ?? 'this_fy',
                'fy' => $filters['fy'] ?? $this->getCurrentFY(),
                'start_date' => $dateRange['start']?->toDateString(),
                'end_date' => $dateRange['end']?->toDateString(),
                'label' => $dateRange['label'],
            ],
            'kpis' => $kpis,
            'yearly_sales' => $yearlySales,
            'monthly_sales' => $monthlySales,
            'product_monthly_qty' => $productMonthlyQty,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'collections_vs_outstanding' => $collectionsVsOutstanding,
            'pipeline_funnel' => $pipelineFunnel,
            'receivables_aging' => $receivablesAging,
            'operational_status' => $operationalStatus,
            'filter_options' => $filterOptions,
        ];
    }

    /**
     * Resolve start and end dates from filter parameters
     */
    public function resolveDateRange(array $filters): array
    {
        $now = Carbon::now();
        $period = $filters['period'] ?? 'this_fy';
        $fy = $filters['fy'] ?? null;

        // If explicit FY is provided without a conflicting period
        if ($fy && $fy !== 'all' && empty($filters['period'])) {
            $period = 'fy';
        }

        switch ($period) {
            case 'this_month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'label' => $now->format('F Y'),
                ];
            case 'last_month':
                $lastMonth = $now->copy()->subMonth();

                return [
                    'start' => $lastMonth->copy()->startOfMonth(),
                    'end' => $lastMonth->copy()->endOfMonth(),
                    'label' => $lastMonth->format('F Y'),
                ];
            case 'this_quarter':
                $qStart = $now->copy()->firstOfQuarter();
                $qEnd = $now->copy()->lastOfQuarter();

                return [
                    'start' => $qStart,
                    'end' => $qEnd,
                    'label' => 'Q'.ceil($now->month / 3).' ('.$qStart->format('M').' - '.$qEnd->format('M Y').')',
                ];
            case 'last_fy':
                $currentYear = ($now->month >= 4) ? $now->year : $now->year - 1;
                $lastYear = $currentYear - 1;

                return [
                    'start' => Carbon::create($lastYear, 4, 1)->startOfDay(),
                    'end' => Carbon::create($lastYear + 1, 3, 31)->endOfDay(),
                    'label' => 'FY '.$lastYear.'-'.substr((string) ($lastYear + 1), 2),
                ];
            case 'all':
                return [
                    'start' => null,
                    'end' => null,
                    'label' => 'All Time',
                ];
            case 'fy':
                if ($fy && preg_match('/^(\d{4})-(\d{2})$/', $fy, $m)) {
                    $y1 = (int) $m[1];

                    return [
                        'start' => Carbon::create($y1, 4, 1)->startOfDay(),
                        'end' => Carbon::create($y1 + 1, 3, 31)->endOfDay(),
                        'label' => 'FY '.$fy,
                    ];
                }
                // Fallthrough to this_fy
            case 'this_fy':
            default:
                $startYear = ($now->month >= 4) ? $now->year : $now->year - 1;

                return [
                    'start' => Carbon::create($startYear, 4, 1)->startOfDay(),
                    'end' => Carbon::create($startYear + 1, 3, 31)->endOfDay(),
                    'label' => 'FY '.$startYear.'-'.substr((string) ($startYear + 1), 2),
                ];
        }
    }

    /**
     * Get current Indian Financial Year string (e.g. "2026-27")
     */
    public function getCurrentFY(): string
    {
        $now = Carbon::now();
        $startYear = ($now->month >= 4) ? $now->year : $now->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), 2);
    }

    /**
     * Top KPI Bento (6 core metrics with comparisons)
     */
    public function getKpis(?int $tenantId, array $dateRange, array $filters): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // 1. Total Invoiced Sales (Filtered by Date Range if specified, and customer/product)
        $invQuery = Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)));
        $this->applyInvoiceFilters($invQuery, $dateRange, $filters);
        $totalSales = (float) $invQuery->sum('total_amount');

        // All-Time Invoiced Sales baseline
        $allTimeSales = (float) Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->sum('total_amount');

        // 2. Current Month Sales vs Previous Month Sales (MoM growth)
        $thisMonthSales = (float) Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $lastMonthSales = (float) Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereBetween('invoice_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $momGrowth = 0;
        if ($lastMonthSales > 0) {
            $momGrowth = round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1);
        } elseif ($thisMonthSales > 0) {
            $momGrowth = 100;
        }

        // 3. Outstanding Receivables (AR)
        $outstandingAR = (float) Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->sum('balance_due');
        $overdueAR = (float) Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->where('due_date', '<', $now->toDateString())
            ->where('balance_due', '>', 0)
            ->sum('balance_due');

        // 4. Total Collections (Bank Receipts - Exclude internal advance offsets to prevent double-counting)
        $rcpQuery = PaymentReceipt::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->where('payment_mode', '!=', 'ADVANCE_OFFSET');
        if ($dateRange['start'] && $dateRange['end']) {
            $rcpQuery->whereBetween('receipt_date', [$dateRange['start'], $dateRange['end']]);
        }
        if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
            $rcpQuery->where('customer_id', $filters['customer_id']);
        }
        $totalCollections = (float) $rcpQuery->sum('amount_received');
        $allTimeCollections = (float) PaymentReceipt::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->where('payment_mode', '!=', 'ADVANCE_OFFSET')
            ->sum('amount_received');
        $collectionRate = $allTimeSales > 0 ? round(($allTimeCollections / $allTimeSales) * 100, 1) : 0;

        // 5. Active Sales Orders / Open Commitments
        $activeOrdersQuery = SalesOrder::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'PARTIALLY_DISPATCHED', 'IN_PRODUCTION']);
        if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
            $activeOrdersQuery->where('customer_id', $filters['customer_id']);
        }
        $activeOrdersCount = $activeOrdersQuery->count();
        $activeOrdersValue = (float) $activeOrdersQuery->sum('total_amount');

        // Open items & pending qty
        $openOrderLines = SalesOrderItem::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereIn('status', ['PENDING', 'PARTIAL'])
            ->count();
        $pendingDispatchQty = (float) SalesOrderItem::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereIn('status', ['PENDING', 'PARTIAL'])
            ->sum('balance_qty');

        // 6. Active Leads & Pipeline
        $leadsQuery = Lead::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->whereNotIn('stage', ['WON', 'LOST']);
        $activeLeadsCount = $leadsQuery->count();
        $pipelineValue = (float) $leadsQuery->sum('estimated_value');
        $samplesInTrial = Sample::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereIn('trial_status', ['Pending', 'Pending Evaluation', 'In Trial'])
            ->count();

        return [
            'total_sales' => $totalSales,
            'all_time_sales' => $allTimeSales,
            'this_month_sales' => $thisMonthSales,
            'last_month_sales' => $lastMonthSales,
            'mom_growth_pct' => $momGrowth,
            'outstanding_ar' => $outstandingAR,
            'overdue_ar' => $overdueAR,
            'total_collections' => $totalCollections,
            'all_time_collections' => $allTimeCollections,
            'collection_rate_pct' => $collectionRate,
            'active_orders_count' => $activeOrdersCount,
            'active_orders_value' => $activeOrdersValue,
            'open_order_lines' => $openOrderLines,
            'pending_dispatch_qty' => $pendingDispatchQty,
            'active_leads_count' => $activeLeadsCount,
            'pipeline_value' => $pipelineValue,
            'samples_in_trial_count' => $samplesInTrial,
        ];
    }

    /**
     * Yearly Sales Historical Trend
     */
    public function getYearlySalesTrend(?int $tenantId, array $filters): array
    {
        $invoices = Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->when(! empty($filters['customer_id']) && $filters['customer_id'] !== 'all', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            })
            ->get(['id', 'invoice_date', 'total_amount']);

        // Group by Indian Financial Year
        $fyGroups = [];
        foreach ($invoices as $inv) {
            $date = Carbon::parse($inv->invoice_date);
            $startYear = ($date->month >= 4) ? $date->year : $date->year - 1;
            $fyKey = 'FY '.$startYear.'-'.substr((string) ($startYear + 1), 2);

            if (! isset($fyGroups[$fyKey])) {
                $fyGroups[$fyKey] = [
                    'fy' => $fyKey,
                    'start_year' => $startYear,
                    'total_sales' => 0.0,
                    'invoice_count' => 0,
                ];
            }
            $fyGroups[$fyKey]['total_sales'] += (float) $inv->total_amount;
            $fyGroups[$fyKey]['invoice_count'] += 1;
        }

        // Ensure at least recent 3 FYs exist for visualization
        $now = Carbon::now();
        $currStartYear = ($now->month >= 4) ? $now->year : $now->year - 1;
        for ($y = $currStartYear - 2; $y <= $currStartYear; $y++) {
            $key = 'FY '.$y.'-'.substr((string) ($y + 1), 2);
            if (! isset($fyGroups[$key])) {
                $fyGroups[$key] = [
                    'fy' => $key,
                    'start_year' => $y,
                    'total_sales' => 0.0,
                    'invoice_count' => 0,
                ];
            }
        }

        // Sort by start year ascending
        usort($fyGroups, fn ($a, $b) => $a['start_year'] <=> $b['start_year']);

        return [
            'labels' => array_column($fyGroups, 'fy'),
            'values' => array_column($fyGroups, 'total_sales'),
            'counts' => array_column($fyGroups, 'invoice_count'),
        ];
    }

    /**
     * Monthly Sales Trend for the selected FY (Apr–Mar)
     */
    public function getMonthlySalesTrend(?int $tenantId, array $dateRange, array $filters): array
    {
        $now = Carbon::now();
        // Determine reference year for the 12-month FY cycle
        $startYear = ($now->month >= 4) ? $now->year : $now->year - 1;
        if (! empty($filters['fy']) && preg_match('/^(\d{4})-(\d{2})$/', $filters['fy'], $m)) {
            $startYear = (int) $m[1];
        }

        // Build 12 months array [Apr ... Mar]
        $months = [];
        $labels = [];
        $keys = [];
        $currentMonthKey = $now->format('Y-m');

        for ($i = 0; $i < 12; $i++) {
            $mNum = ($i + 4 <= 12) ? ($i + 4) : ($i + 4 - 12);
            $yNum = ($i + 4 <= 12) ? $startYear : ($startYear + 1);
            $dt = Carbon::create($yNum, $mNum, 1);
            $key = $dt->format('Y-m');
            $keys[] = $key;
            $labels[] = $dt->format('M');
            $months[$key] = [
                'month_name' => $dt->format('M Y'),
                'short_label' => $dt->format('M'),
                'year_month' => $key,
                'sales' => 0.0,
                'invoice_count' => 0,
                'is_current' => ($key === $currentMonthKey),
            ];
        }

        $invQuery = Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereBetween('invoice_date', [
                Carbon::create($startYear, 4, 1)->startOfDay(),
                Carbon::create($startYear + 1, 3, 31)->endOfDay(),
            ]);

        if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
            $invQuery->where('customer_id', $filters['customer_id']);
        }

        $invoices = $invQuery->get(['id', 'invoice_date', 'total_amount']);

        foreach ($invoices as $inv) {
            $key = Carbon::parse($inv->invoice_date)->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['sales'] += (float) $inv->total_amount;
                $months[$key]['invoice_count'] += 1;
            }
        }

        return [
            'labels' => $labels,
            'keys' => $keys,
            'values' => array_values(array_column($months, 'sales')),
            'counts' => array_values(array_column($months, 'invoice_count')),
            'current_month_index' => array_search($currentMonthKey, $keys),
            'fy_label' => 'FY '.$startYear.'-'.substr((string) ($startYear + 1), 2),
        ];
    }

    /**
     * Product-wise Monthly Sales Quantity (KG)
     */
    public function getProductMonthlySalesQty(?int $tenantId, array $dateRange, array $filters): array
    {
        $now = Carbon::now();
        $startYear = ($now->month >= 4) ? $now->year : $now->year - 1;
        if (! empty($filters['fy']) && preg_match('/^(\d{4})-(\d{2})$/', $filters['fy'], $m)) {
            $startYear = (int) $m[1];
        }

        // Build 12 months list
        $keys = [];
        $labels = [];
        for ($i = 0; $i < 12; $i++) {
            $mNum = ($i + 4 <= 12) ? ($i + 4) : ($i + 4 - 12);
            $yNum = ($i + 4 <= 12) ? $startYear : ($startYear + 1);
            $dt = Carbon::create($yNum, $mNum, 1);
            $keys[] = $dt->format('Y-m');
            $labels[] = $dt->format('M');
        }

        // Query Invoice Items joined with Invoices
        $itemsQuery = InvoiceItem::with(['invoice', 'product'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('invoice', function ($q) use ($startYear, $filters) {
                $q->whereBetween('invoice_date', [
                    Carbon::create($startYear, 4, 1)->startOfDay(),
                    Carbon::create($startYear + 1, 3, 31)->endOfDay(),
                ]);
                if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
                    $q->where('customer_id', $filters['customer_id']);
                }
            });

        if (! empty($filters['product_id']) && $filters['product_id'] !== 'all') {
            $itemsQuery->where('product_id', $filters['product_id']);
        }

        $items = $itemsQuery->get();

        // Group quantities by product and month
        $monthlyAggregates = array_fill_keys($keys, 0.0);
        $byProduct = [];

        foreach ($items as $it) {
            if (! $it->invoice) {
                continue;
            }
            $mKey = Carbon::parse($it->invoice->invoice_date)->format('Y-m');
            if (! in_array($mKey, $keys)) {
                continue;
            }

            $prodId = $it->product_id ?: 0;
            $prodName = $it->product?->product_name ?? 'Other Dehydrated Items';
            $qty = (float) $it->quantity;

            if (! isset($byProduct[$prodId])) {
                $byProduct[$prodId] = [
                    'product_id' => $prodId,
                    'product_name' => $prodName,
                    'total_qty' => 0.0,
                    'monthly' => array_fill_keys($keys, 0.0),
                ];
            }

            $byProduct[$prodId]['monthly'][$mKey] += $qty;
            $byProduct[$prodId]['total_qty'] += $qty;
            $monthlyAggregates[$mKey] += $qty;
        }

        // Sort products by total quantity descending
        uasort($byProduct, fn ($a, $b) => $b['total_qty'] <=> $a['total_qty']);

        // Format datasets for Chart.js
        $palette = ['#091315', '#D7FF53', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'];
        $datasets = [];
        $idx = 0;

        foreach ($byProduct as $pId => $pData) {
            $color = $palette[$idx % count($palette)];
            $datasets[] = [
                'label' => $pData['product_name'],
                'data' => array_values($pData['monthly']),
                'borderColor' => $color,
                'backgroundColor' => ($color === '#D7FF53') ? 'rgba(215, 255, 83, 0.4)' : $color,
                'total_qty' => $pData['total_qty'],
            ];
            $idx++;
            if ($idx >= 5) {
                break;
            } // Top 5 in multi-chart
        }

        return [
            'labels' => $labels,
            'keys' => $keys,
            'total_monthly_qty' => array_values($monthlyAggregates),
            'datasets' => $datasets,
            'products_list' => array_values(array_map(fn ($p) => [
                'id' => $p['product_id'],
                'name' => $p['product_name'],
                'total_qty' => $p['total_qty'],
            ], $byProduct)),
        ];
    }

    /**
     * Top Products by Revenue
     */
    public function getTopProductsByRevenue(?int $tenantId, array $dateRange, array $filters, int $limit = 5): array
    {
        $itemsQuery = InvoiceItem::with(['invoice', 'product'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('invoice', function ($q) use ($dateRange, $filters) {
                if ($dateRange['start'] && $dateRange['end']) {
                    $q->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']]);
                }
                if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
                    $q->where('customer_id', $filters['customer_id']);
                }
            });

        $items = $itemsQuery->get();

        $byProduct = [];
        $totalRevenueAll = 0.0;

        foreach ($items as $it) {
            $pId = $it->product_id ?: 0;
            $pName = $it->product?->product_name ?? 'Standard Dehydrated Item';
            $pCode = $it->product?->product_code ?? 'PRD';
            $amt = (float) $it->amount;
            $qty = (float) $it->quantity;

            if (! isset($byProduct[$pId])) {
                $byProduct[$pId] = [
                    'product_id' => $pId,
                    'product_name' => $pName,
                    'product_code' => $pCode,
                    'revenue' => 0.0,
                    'quantity' => 0.0,
                    'uom' => $it->product?->uom ?? 'KG',
                ];
            }

            $byProduct[$pId]['revenue'] += $amt;
            $byProduct[$pId]['quantity'] += $qty;
            $totalRevenueAll += $amt;
        }

        // Sort by revenue descending
        usort($byProduct, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $top = array_slice($byProduct, 0, $limit);

        // Add percentage of total
        foreach ($top as &$prod) {
            $prod['share_pct'] = $totalRevenueAll > 0 ? round(($prod['revenue'] / $totalRevenueAll) * 100, 1) : 0;
        }

        return [
            'labels' => array_column($top, 'product_name'),
            'values' => array_column($top, 'revenue'),
            'quantities' => array_column($top, 'quantity'),
            'items' => $top,
            'total_revenue' => $totalRevenueAll,
        ];
    }

    /**
     * Top Customers by Sales Turnover
     */
    public function getTopCustomersBySales(?int $tenantId, array $dateRange, array $filters, int $limit = 5): array
    {
        $invQuery = Invoice::with('customer')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $this->applyInvoiceFilters($invQuery, $dateRange, $filters);

        $invoices = $invQuery->get(['id', 'customer_id', 'total_amount', 'balance_due', 'amount_received']);

        $byCustomer = [];
        $totalBilledAll = 0.0;

        foreach ($invoices as $inv) {
            $cId = $inv->customer_id ?: 0;
            $cName = $inv->customer?->company_name ?? 'Direct Counterparty';
            $cCity = $inv->customer?->city ?? '';
            $billed = (float) $inv->total_amount;
            $received = (float) $inv->amount_received;
            $balance = (float) $inv->balance_due;

            if (! isset($byCustomer[$cId])) {
                $byCustomer[$cId] = [
                    'customer_id' => $cId,
                    'customer_name' => $cName,
                    'city' => $cCity,
                    'billed_amount' => 0.0,
                    'received_amount' => 0.0,
                    'balance_due' => 0.0,
                    'invoice_count' => 0,
                ];
            }

            $byCustomer[$cId]['billed_amount'] += $billed;
            $byCustomer[$cId]['received_amount'] += $received;
            $byCustomer[$cId]['balance_due'] += $balance;
            $byCustomer[$cId]['invoice_count'] += 1;
            $totalBilledAll += $billed;
        }

        usort($byCustomer, fn ($a, $b) => $b['billed_amount'] <=> $a['billed_amount']);

        $top = array_slice($byCustomer, 0, $limit);

        foreach ($top as &$cust) {
            $cust['share_pct'] = $totalBilledAll > 0 ? round(($cust['billed_amount'] / $totalBilledAll) * 100, 1) : 0;
            $cust['recovery_pct'] = $cust['billed_amount'] > 0 ? round(($cust['received_amount'] / $cust['billed_amount']) * 100, 1) : 0;
        }

        return [
            'labels' => array_column($top, 'customer_name'),
            'values' => array_column($top, 'billed_amount'),
            'items' => $top,
            'total_billed' => $totalBilledAll,
        ];
    }

    /**
     * Collections vs Outstanding Cash Flow Health (Monthly Invoiced vs Collected vs Outstanding)
     */
    public function getCollectionsVsOutstanding(?int $tenantId, array $dateRange, array $filters): array
    {
        $now = Carbon::now();
        $startYear = ($now->month >= 4) ? $now->year : $now->year - 1;
        if (! empty($filters['fy']) && preg_match('/^(\d{4})-(\d{2})$/', $filters['fy'], $m)) {
            $startYear = (int) $m[1];
        }

        $keys = [];
        $labels = [];
        $monthly = [];

        for ($i = 0; $i < 12; $i++) {
            $mNum = ($i + 4 <= 12) ? ($i + 4) : ($i + 4 - 12);
            $yNum = ($i + 4 <= 12) ? $startYear : ($startYear + 1);
            $dt = Carbon::create($yNum, $mNum, 1);
            $key = $dt->format('Y-m');
            $keys[] = $key;
            $labels[] = $dt->format('M');
            $monthly[$key] = [
                'invoiced' => 0.0,
                'collected' => 0.0,
                'outstanding' => 0.0,
            ];
        }

        // Invoices within this FY
        $invoices = Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->whereBetween('invoice_date', [
                Carbon::create($startYear, 4, 1)->startOfDay(),
                Carbon::create($startYear + 1, 3, 31)->endOfDay(),
            ])
            ->when(! empty($filters['customer_id']) && $filters['customer_id'] !== 'all', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            })
            ->get(['invoice_date', 'total_amount', 'balance_due']);

        foreach ($invoices as $inv) {
            $k = Carbon::parse($inv->invoice_date)->format('Y-m');
            if (isset($monthly[$k])) {
                $monthly[$k]['invoiced'] += (float) $inv->total_amount;
                $monthly[$k]['outstanding'] += (float) $inv->balance_due;
            }
        }

        // Payment Receipts within this FY (Real bank receipts only)
        $receipts = PaymentReceipt::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->where('payment_mode', '!=', 'ADVANCE_OFFSET')
            ->whereBetween('receipt_date', [
                Carbon::create($startYear, 4, 1)->startOfDay(),
                Carbon::create($startYear + 1, 3, 31)->endOfDay(),
            ])
            ->when(! empty($filters['customer_id']) && $filters['customer_id'] !== 'all', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            })
            ->get(['receipt_date', 'amount_received']);

        foreach ($receipts as $rcp) {
            $k = Carbon::parse($rcp->receipt_date)->format('Y-m');
            if (isset($monthly[$k])) {
                $monthly[$k]['collected'] += (float) $rcp->amount_received;
            }
        }

        return [
            'labels' => $labels,
            'keys' => $keys,
            'invoiced' => array_values(array_column($monthly, 'invoiced')),
            'collected' => array_values(array_column($monthly, 'collected')),
            'outstanding' => array_values(array_column($monthly, 'outstanding')),
        ];
    }

    /**
     * Sales Pipeline Funnel (5 stages: RFQ, Sample Trial, Quotation, Negotiation, Won)
     */
    public function getSalesPipelineFunnel(?int $tenantId): array
    {
        $stages = [
            'LEAD' => [
                'stage_key' => 'LEAD',
                'name' => 'Inquiries & RFQs',
                'description' => 'Initial lead inquiries & buyer RFQs',
                'count' => 0,
                'value' => 0.0,
                'color' => '#64748B',
            ],
            'SAMPLE_REQUESTED' => [
                'stage_key' => 'SAMPLE_REQUESTED',
                'name' => 'Sample Trial & COA',
                'description' => 'Lab evaluation & trial dispatch',
                'count' => 0,
                'value' => 0.0,
                'color' => '#3B82F6',
            ],
            'QUOTATION_SENT' => [
                'stage_key' => 'QUOTATION_SENT',
                'name' => 'Quotation & Proforma',
                'description' => 'Rate quotation & spec sheets shared',
                'count' => 0,
                'value' => 0.0,
                'color' => '#F59E0B',
            ],
            'NEGOTIATION' => [
                'stage_key' => 'NEGOTIATION',
                'name' => 'Commercial Negotiation',
                'description' => 'Payment terms & volume closure',
                'count' => 0,
                'value' => 0.0,
                'color' => '#8B5CF6',
            ],
            'WON' => [
                'stage_key' => 'WON',
                'name' => 'Closed Won (Clients)',
                'description' => 'Converted purchase contract',
                'count' => 0,
                'value' => 0.0,
                'color' => '#10B981',
            ],
        ];

        $leads = Lead::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->get(['stage', 'estimated_value']);
        $totalPipelineValue = 0.0;
        $totalLeads = $leads->count();

        foreach ($leads as $lead) {
            $stg = $lead->stage ?? 'LEAD';
            if (isset($stages[$stg])) {
                $stages[$stg]['count'] += 1;
                $stages[$stg]['value'] += (float) $lead->estimated_value;
                $totalPipelineValue += (float) $lead->estimated_value;
            }
        }

        // Calculate conversion progression
        $stagesList = array_values($stages);
        $prevCount = 0;
        foreach ($stagesList as $idx => &$s) {
            $s['share_pct'] = $totalLeads > 0 ? round(($s['count'] / $totalLeads) * 100, 1) : 0;
            if ($idx === 0) {
                $s['conversion_rate'] = 100;
            } else {
                $s['conversion_rate'] = ($prevCount > 0) ? round(($s['count'] / $prevCount) * 100, 1) : 0;
            }
            $prevCount = $s['count'];
        }

        return [
            'stages' => $stagesList,
            'total_leads_count' => $totalLeads,
            'total_pipeline_value' => $totalPipelineValue,
        ];
    }

    /**
     * Receivables Aging Analysis Buckets (Not Due, 1-15d, 16-30d, 31-60d, 60+d)
     */
    public function getReceivablesAging(?int $tenantId): array
    {
        $today = Carbon::today();

        $invoices = Invoice::with('customer')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('balance_due', '>', 0)
            ->get(['id', 'invoice_number', 'customer_id', 'due_date', 'total_amount', 'balance_due', 'status']);

        $buckets = [
            'not_due' => [
                'key' => 'not_due',
                'label' => 'Current / Not Due',
                'subtext' => 'Payment term active',
                'count' => 0,
                'amount' => 0.0,
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'bar_color' => '#10B981',
            ],
            '1_15' => [
                'key' => '1_15',
                'label' => '1–15 Days Overdue',
                'subtext' => 'Gentle reminder window',
                'count' => 0,
                'amount' => 0.0,
                'badge_class' => 'bg-amber-50 text-amber-700 border-amber-200',
                'bar_color' => '#F59E0B',
            ],
            '16_30' => [
                'key' => '16_30',
                'label' => '16–30 Days Overdue',
                'subtext' => 'Priority follow-up',
                'count' => 0,
                'amount' => 0.0,
                'badge_class' => 'bg-orange-50 text-orange-700 border-orange-200',
                'bar_color' => '#F97316',
            ],
            '31_60' => [
                'key' => '31_60',
                'label' => '31–60 Days Overdue',
                'subtext' => 'Escalation required',
                'count' => 0,
                'amount' => 0.0,
                'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200',
                'bar_color' => '#EF4444',
            ],
            '60_plus' => [
                'key' => '60_plus',
                'label' => '60+ Days Overdue',
                'subtext' => 'Critical credit hold',
                'count' => 0,
                'amount' => 0.0,
                'badge_class' => 'bg-red-100 text-red-900 border-red-300 font-black',
                'bar_color' => '#991B1B',
            ],
        ];

        $totalOutstanding = 0.0;

        foreach ($invoices as $inv) {
            $bal = (float) $inv->balance_due;
            $totalOutstanding += $bal;
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : $today;

            if ($dueDate->gte($today)) {
                $buckets['not_due']['count'] += 1;
                $buckets['not_due']['amount'] += $bal;
            } else {
                $daysOverdue = (int) $dueDate->diffInDays($today);
                if ($daysOverdue <= 15) {
                    $buckets['1_15']['count'] += 1;
                    $buckets['1_15']['amount'] += $bal;
                } elseif ($daysOverdue <= 30) {
                    $buckets['16_30']['count'] += 1;
                    $buckets['16_30']['amount'] += $bal;
                } elseif ($daysOverdue <= 60) {
                    $buckets['31_60']['count'] += 1;
                    $buckets['31_60']['amount'] += $bal;
                } else {
                    $buckets['60_plus']['count'] += 1;
                    $buckets['60_plus']['amount'] += $bal;
                }
            }
        }

        $list = array_values($buckets);
        foreach ($list as &$b) {
            $b['share_pct'] = $totalOutstanding > 0 ? round(($b['amount'] / $totalOutstanding) * 100, 1) : 0;
        }

        return [
            'buckets' => $list,
            'total_outstanding' => $totalOutstanding,
            'total_invoices_count' => $invoices->count(),
        ];
    }

    /**
     * Operational Orders & Shipment Fulfillment Status breakdown
     */
    public function getOperationalStatus(?int $tenantId): array
    {
        // Sales Orders Status
        $orderCounts = [
            'PENDING' => 0,
            'IN_PRODUCTION' => 0,
            'READY' => 0,
            'COMPLETED' => 0,
        ];

        $orders = SalesOrder::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->get(['status', 'total_amount']);
        foreach ($orders as $so) {
            $st = strtoupper($so->status ?? 'PENDING');
            if (in_array($st, ['PENDING', 'DRAFT', 'CREATED'])) {
                $orderCounts['PENDING'] += 1;
            } elseif (in_array($st, ['CONFIRMED', 'IN_PRODUCTION', 'PARTIALLY_DISPATCHED', 'PARTIAL'])) {
                $orderCounts['IN_PRODUCTION'] += 1;
            } elseif (in_array($st, ['READY', 'READY_FOR_DISPATCH'])) {
                $orderCounts['READY'] += 1;
            } else {
                $orderCounts['COMPLETED'] += 1;
            }
        }

        // Shipments Status
        $shipmentCounts = [
            'PENDING_DISPATCH' => 0,
            'IN_TRANSIT' => 0,
            'DELIVERED' => 0,
            'DELAYED' => 0,
        ];

        $shipments = CommercialShipment::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->get(['status']);
        foreach ($shipments as $shp) {
            $st = strtoupper($shp->status ?? 'PENDING');
            if (in_array($st, ['PENDING', 'PENDING_DISPATCH', 'PLANNED'])) {
                $shipmentCounts['PENDING_DISPATCH'] += 1;
            } elseif (in_array($st, ['DISPATCHED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'])) {
                $shipmentCounts['IN_TRANSIT'] += 1;
            } elseif (in_array($st, ['DELIVERED', 'COMPLETED', 'RECEIVED'])) {
                $shipmentCounts['DELIVERED'] += 1;
            } else {
                $shipmentCounts['DELAYED'] += 1;
            }
        }

        return [
            'orders' => $orderCounts,
            'shipments' => $shipmentCounts,
            'total_orders' => $orders->count(),
            'total_shipments' => $shipments->count(),
        ];
    }

    /**
     * Populate filter dropdown options
     */
    public function getFilterOptions(?int $tenantId): array
    {
        // 1. Available FYs from invoices and current date
        $now = Carbon::now();
        $currStartYear = ($now->month >= 4) ? $now->year : $now->year - 1;
        $years = [$currStartYear, $currStartYear - 1];

        $invDates = Invoice::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))->pluck('invoice_date');
        foreach ($invDates as $d) {
            if ($d) {
                $dt = Carbon::parse($d);
                $sy = ($dt->month >= 4) ? $dt->year : $dt->year - 1;
                if (! in_array($sy, $years)) {
                    $years[] = $sy;
                }
            }
        }
        rsort($years);

        $fyList = [];
        foreach ($years as $y) {
            $key = $y.'-'.substr((string) ($y + 1), 2);
            $fyList[] = [
                'key' => $key,
                'label' => 'FY '.$key,
            ];
        }

        // 2. Customers
        $customers = Customer::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->orderBy('company_name')
            ->get(['id', 'company_name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->company_name])
            ->toArray();

        // 3. Products
        $products = Product::query()->when($tenantId, fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)))
            ->orderBy('product_name')
            ->get(['id', 'product_name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->product_name])
            ->toArray();

        return [
            'financial_years' => $fyList,
            'current_fy' => $this->getCurrentFY(),
            'customers' => $customers,
            'products' => $products,
        ];
    }

    /**
     * Helper to apply common invoice filters
     */
    protected function applyInvoiceFilters($query, array $dateRange, array $filters): void
    {
        if ($dateRange['start'] && $dateRange['end']) {
            $query->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']]);
        }

        if (! empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['product_id']) && $filters['product_id'] !== 'all') {
            $query->whereHas('items', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }
    }
}
