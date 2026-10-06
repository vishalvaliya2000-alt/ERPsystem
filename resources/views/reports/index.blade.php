@extends('layouts.app')

@section('title', 'Financial & Operational Reports')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Reports Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('reports.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                📊 Reports Hub
            </a>
            <a href="{{ route('reports.balance-sheet') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏛️ Balance Sheet
            </a>
            <a href="{{ route('reports.bill-profit') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                💰 Bill-Wise Profit
            </a>
            <a href="{{ route('reports.party-profit') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                👥 Party Profitability
            </a>
            <a href="{{ route('reports.ar-aging') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                ⏳ AR Aging
            </a>
            <a href="{{ route('reports.batch-trace') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🔍 Batch Traceability
            </a>
        </div>
        <div class="text-xs text-neutral-500 font-medium">
            Active Firm: <span class="font-bold text-neutral-800">{{ $activeFirm?->firm_name ?? 'Default Firm' }}</span>
        </div>
    </div>

    <!-- Executive KPI Overview Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-neutral-200 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Receivables</span>
                <span class="p-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs">⏳ AR</span>
            </div>
            <div class="text-2xl font-black text-neutral-900 mt-2">₹{{ number_format($stats['total_receivables'], 2) }}</div>
            <div class="text-xs text-neutral-500 mt-1">Pending customer collections</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">MTD Invoiced</span>
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs">📈 MTD</span>
            </div>
            <div class="text-2xl font-black text-blue-600 mt-2">₹{{ number_format($stats['monthly_billed'], 2) }}</div>
            <div class="text-xs text-neutral-500 mt-1">Current month billings</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Gross Margin (MTD)</span>
                <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs">💰 {{ $stats['avg_margin_mtd'] }}%</span>
            </div>
            <div class="text-2xl font-black text-emerald-600 mt-2">₹{{ number_format($stats['monthly_profit'], 2) }}</div>
            <div class="text-xs text-neutral-500 mt-1">Average margin: {{ $stats['avg_margin_mtd'] }}%</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Active Batches</span>
                <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg text-xs">🏭 Batch</span>
            </div>
            <div class="text-2xl font-black text-purple-700 mt-2">{{ $stats['batch_count'] }}</div>
            <div class="text-xs text-neutral-500 mt-1">Production batches on file</div>
        </div>
    </div>

    <!-- Bento Grid of Detailed Reports -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- 1. Balance Sheet -->
        <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-colors">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-neutral-900 text-white flex items-center justify-center text-xl shadow-xs mb-4">
                    🏛️
                </div>
                <h3 class="text-base font-bold text-neutral-900">Statutory Balance Sheet</h3>
                <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                    Formal financial statement detailing Current & Fixed Assets, Current & Long-Term Liabilities, Owner Equity and Period Net Profit with automated invariant verification.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">As-of Date Picker</span>
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Firm Scoped</span>
                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg font-medium">A = L + E Verified</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between">
                <a href="{{ route('reports.balance-sheet') }}" class="text-xs font-bold text-neutral-900 hover:underline flex items-center gap-1">
                    Open Statement &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.balance-sheet.pdf') }}" title="Download PDF" class="p-2 bg-neutral-100 hover:bg-neutral-200 rounded-lg text-xs font-bold text-neutral-700">PDF</a>
                    <a href="{{ route('reports.balance-sheet.excel') }}" title="Download Excel" class="p-2 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-xs font-bold text-emerald-700">XLSX</a>
                </div>
            </div>
        </div>

        <!-- 2. Bill-Wise Profit -->
        <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-colors">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-xs mb-4">
                    💰
                </div>
                <h3 class="text-base font-bold text-neutral-900">Bill-Wise Profit & Margin</h3>
                <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                    Analyze profitability per invoice with item cost breakdown, gross profit, margin percentage, and color-coded health indicators (High, Healthy, Low, Negative).
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Invoice Line Margins</span>
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Health Badges</span>
                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg font-medium">LR & E-Way Link</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between">
                <a href="{{ route('reports.bill-profit') }}" class="text-xs font-bold text-neutral-900 hover:underline flex items-center gap-1">
                    Analyze Bills &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.bill-profit.pdf') }}" title="Download PDF" class="p-2 bg-neutral-100 hover:bg-neutral-200 rounded-lg text-xs font-bold text-neutral-700">PDF</a>
                    <a href="{{ route('reports.bill-profit.excel') }}" title="Download Excel" class="p-2 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-xs font-bold text-emerald-700">XLSX</a>
                </div>
            </div>
        </div>

        <!-- 3. Party-Wise Profitability -->
        <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-colors">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl shadow-xs mb-4">
                    👥
                </div>
                <h3 class="text-base font-bold text-neutral-900">Party-Wise Profitability</h3>
                <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                    Rank and benchmark customers by gross margin contribution, cumulative billings, total revenue, and compare profit share against current outstanding balances.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Top Accounts Ranking</span>
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Profit Share %</span>
                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg font-medium">Outstanding Risk</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between">
                <a href="{{ route('reports.party-profit') }}" class="text-xs font-bold text-neutral-900 hover:underline flex items-center gap-1">
                    View Rankings &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.party-profit.pdf') }}" title="Download PDF" class="p-2 bg-neutral-100 hover:bg-neutral-200 rounded-lg text-xs font-bold text-neutral-700">PDF</a>
                    <a href="{{ route('reports.party-profit.excel') }}" title="Download Excel" class="p-2 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-xs font-bold text-emerald-700">XLSX</a>
                </div>
            </div>
        </div>

        <!-- 4. Accounts Receivable Aging -->
        <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-colors">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl shadow-xs mb-4">
                    ⏳
                </div>
                <h3 class="text-base font-bold text-neutral-900">Accounts Receivable (AR) Aging</h3>
                <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                    Categorize outstanding receivables into standard aging intervals (Current, 1–30, 31–60, 61–90, 90+ days) with 1-click WhatsApp payment reminders.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">5 Aging Buckets</span>
                    <span class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-lg font-medium">90+ Critical Alert</span>
                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg font-medium">WhatsApp Remind</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between">
                <a href="{{ route('reports.ar-aging') }}" class="text-xs font-bold text-neutral-900 hover:underline flex items-center gap-1">
                    Open Aging Schedule &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.ar-aging.pdf') }}" title="Download PDF" class="p-2 bg-neutral-100 hover:bg-neutral-200 rounded-lg text-xs font-bold text-neutral-700">PDF</a>
                    <a href="{{ route('reports.ar-aging.excel') }}" title="Download Excel" class="p-2 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-xs font-bold text-emerald-700">XLSX</a>
                </div>
            </div>
        </div>

        <!-- 5. Batch & Serial Traceability -->
        <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-neutral-400 transition-colors">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-purple-600 text-white flex items-center justify-center text-xl shadow-xs mb-4">
                    🔍
                </div>
                <h3 class="text-base font-bold text-neutral-900">Batch & Serial Traceability</h3>
                <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                    Statutory full-chain audit trail: Raw Material Lot &rarr; Production Batch &rarr; QC Sample Trials &rarr; Commercial Shipments (LR) &rarr; Tax Invoices &rarr; E-Way Bills.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">6-Stage Trace</span>
                    <span class="px-2.5 py-1 bg-neutral-100 text-neutral-700 rounded-lg font-medium">Recall Audit Ready</span>
                    <span class="px-2.5 py-1 bg-purple-50 text-purple-700 rounded-lg font-medium">Visual Timeline</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between">
                <a href="{{ route('reports.batch-trace') }}" class="text-xs font-bold text-neutral-900 hover:underline flex items-center gap-1">
                    Trace Lot/Batch &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.batch-trace.pdf') }}" title="Download PDF" class="p-2 bg-neutral-100 hover:bg-neutral-200 rounded-lg text-xs font-bold text-neutral-700">PDF</a>
                    <a href="{{ route('reports.batch-trace.excel') }}" title="Download Excel" class="p-2 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-xs font-bold text-emerald-700">XLSX</a>
                </div>
            </div>
        </div>

        <!-- 6. Master Excel Export Hub Link -->
        <div class="bg-neutral-900 text-white rounded-2xl p-6 shadow-xs flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-neutral-800 text-[#D7FF53] flex items-center justify-center text-xl shadow-xs mb-4">
                    ⚡
                </div>
                <h3 class="text-base font-bold text-white">Full Database Backup / Export</h3>
                <p class="text-xs text-neutral-400 mt-2 leading-relaxed">
                    Download an all-in-one multi-sheet Excel workbook containing your Master Customers, Products, Sales Orders, Shipments, Invoices, and Payment Receipts.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 bg-neutral-800 text-[#D7FF53] rounded-lg font-medium">Multi-Tab Workbook</span>
                    <span class="px-2.5 py-1 bg-neutral-800 text-neutral-300 rounded-lg font-medium">Instant Export</span>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-neutral-800 flex items-center justify-between">
                <a href="{{ route('excel.index') }}" class="text-xs font-bold text-[#D7FF53] hover:underline flex items-center gap-1">
                    Master Export Hub &rarr;
                </a>
                <a href="{{ route('excel.export') }}" class="px-3 py-1.5 bg-[#D7FF53] hover:bg-[#cbf540] text-neutral-950 font-bold rounded-lg text-xs">
                    Download XLSX
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
