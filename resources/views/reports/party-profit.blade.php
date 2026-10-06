@extends('layouts.app')

@section('title', 'Party-Wise Profitability Ranking')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Reports Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('reports.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📊 Reports Hub
            </a>
            <a href="{{ route('reports.balance-sheet') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏛️ Balance Sheet
            </a>
            <a href="{{ route('reports.bill-profit') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                💰 Bill-Wise Profit
            </a>
            <a href="{{ route('reports.party-profit') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                👥 Party Profitability
            </a>
            <a href="{{ route('reports.ar-aging') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                ⏳ AR Aging
            </a>
            <a href="{{ route('reports.batch-trace') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🔍 Batch Traceability
            </a>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('reports.party-profit.pdf', request()->query()) }}"
               class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📄</span> Export PDF
            </a>
            <a href="{{ route('reports.party-profit.excel', request()->query()) }}"
               class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📊</span> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('reports.party-profit') }}" class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}"
                       class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}"
                       class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">Sort By</label>
                <select name="sort_by" class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
                    <option value="profit_desc" {{ ($filters['sort_by'] ?? '') == 'profit_desc' ? 'selected' : '' }}>Highest Profit</option>
                    <option value="revenue_desc" {{ ($filters['sort_by'] ?? '') == 'revenue_desc' ? 'selected' : '' }}>Highest Revenue</option>
                    <option value="margin_desc" {{ ($filters['sort_by'] ?? '') == 'margin_desc' ? 'selected' : '' }}>Highest Margin %</option>
                    <option value="profit_asc" {{ ($filters['sort_by'] ?? '') == 'profit_asc' ? 'selected' : '' }}>Lowest Profit</option>
                </select>
            </div>

            <div class="self-end">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Apply Sorting
                </button>
            </div>
        </div>

        <div class="text-xs text-neutral-500">
            Total Analyzed Customers: <span class="font-bold text-neutral-800">{{ $data['summary']['total_parties'] }}</span>
        </div>
    </form>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Analyzed Revenue</span>
            <div class="text-xl font-black text-blue-600 mt-1">₹{{ number_format($data['summary']['total_revenue'], 2) }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Across {{ $data['summary']['total_parties'] }} accounts</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Gross Profit</span>
            <div class="text-xl font-black text-emerald-600 mt-1">₹{{ number_format($data['summary']['total_profit'], 2) }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Net gross profit earned</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Top Account</span>
            <div class="text-base font-bold text-neutral-900 truncate mt-1">
                {{ $data['summary']['top_party']['company_name'] ?? 'N/A' }}
            </div>
            <div class="text-[11px] text-emerald-600 font-bold mt-0.5">
                ₹{{ number_format($data['summary']['top_party']['gross_profit'] ?? 0, 2) }} profit
            </div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Avg Profit / Party</span>
            <div class="text-xl font-black text-neutral-900 mt-1">₹{{ number_format($data['summary']['avg_party_profit'], 2) }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Mean contribution</div>
        </div>
    </div>

    <!-- Customer Ranking Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-neutral-800">Customer Account Profitability Benchmarking</h3>
            <span class="text-xs text-neutral-500 font-mono">{{ count($data['parties']) }} accounts</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-neutral-50/75 border-b border-neutral-200/80 text-[11px] font-bold text-neutral-500 uppercase tracking-wider">
                        <th class="py-3 px-4 text-center">Rank</th>
                        <th class="py-3 px-4">Customer / Enterprise</th>
                        <th class="py-3 px-4">State & GSTIN</th>
                        <th class="py-3 px-4 text-center">Invoices</th>
                        <th class="py-3 px-4 text-right">Revenue (₹)</th>
                        <th class="py-3 px-4 text-right">Profit (₹)</th>
                        <th class="py-3 px-4 text-center">Margin %</th>
                        <th class="py-3 px-4">Profit Share %</th>
                        <th class="py-3 px-4 text-right">AR Outstanding (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 font-medium">
                    @forelse($data['parties'] as $idx => $party)
                    <tr class="hover:bg-neutral-50/60 transition-colors">
                        <td class="py-3 px-4 text-center font-bold text-neutral-400">
                            #{{ $idx + 1 }}
                        </td>
                        <td class="py-3 px-4">
                            <a href="{{ route('customers.show', $party['customer_id']) }}" class="font-bold text-neutral-900 hover:underline">
                                {{ $party['company_name'] }}
                            </a>
                            <div class="text-[10px] text-neutral-400 font-mono">{{ $party['customer_code'] }}</div>
                        </td>
                        <td class="py-3 px-4 text-neutral-600">
                            <div>{{ $party['state'] }}</div>
                            <div class="text-[10px] text-neutral-400 font-mono">{{ $party['gst_number'] }}</div>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-neutral-800">
                            {{ $party['invoice_count'] }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-neutral-800">
                            ₹{{ number_format($party['total_revenue'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold {{ $party['gross_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            ₹{{ number_format($party['gross_profit'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-center font-mono font-bold text-neutral-900">
                            {{ $party['margin_percentage'] }}%
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-neutral-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ min(100, max(0, $party['profit_contribution'])) }}%"></div>
                                </div>
                                <span class="font-mono text-[11px] font-bold text-neutral-700">{{ $party['profit_contribution'] }}%</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold {{ $party['total_outstanding'] > 0 ? 'text-amber-600' : 'text-neutral-400' }}">
                            ₹{{ number_format($party['total_outstanding'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-neutral-400">
                            No party profitability records found for the selected period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
