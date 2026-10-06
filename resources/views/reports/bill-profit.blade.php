@extends('layouts.app')

@section('title', 'Bill-Wise Profit & Margin Analysis')

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
            <a href="{{ route('reports.bill-profit') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
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

        <div class="flex items-center gap-2">
            <a href="{{ route('reports.bill-profit.pdf', request()->query()) }}"
               class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📄</span> Export PDF
            </a>
            <a href="{{ route('reports.bill-profit.excel', request()->query()) }}"
               class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📊</span> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('reports.bill-profit') }}" class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
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
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">Customer</label>
                <select name="customer_id" class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden max-w-[200px]">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>
                            {{ $c->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">Min Margin %</label>
                <input type="number" step="0.1" name="min_margin" placeholder="e.g. 15" value="{{ $filters['min_margin'] ?? '' }}"
                       class="w-24 px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
            </div>

            <div class="self-end">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Filter Invoices
                </button>
            </div>
        </div>

        <div class="flex items-center gap-1.5 text-[11px] font-bold">
            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg">High (≥20%): {{ $data['summary']['high_margin_count'] }}</span>
            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg">Healthy (≥10%): {{ $data['summary']['healthy_margin_count'] }}</span>
            <span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg">Low (0-10%): {{ $data['summary']['low_margin_count'] }}</span>
            @if($data['summary']['negative_margin_count'] > 0)
                <span class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-lg">Negative (&lt;0%): {{ $data['summary']['negative_margin_count'] }}</span>
            @endif
        </div>
    </form>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Billed Invoices</span>
            <div class="text-xl font-black text-neutral-900 mt-1">{{ $data['summary']['total_invoices'] }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">₹{{ number_format($data['summary']['total_billed'], 2) }} Gross</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Net Revenue</span>
            <div class="text-xl font-black text-blue-600 mt-1">₹{{ number_format($data['summary']['total_revenue'], 2) }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Excluding GST</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Gross Profit</span>
            <div class="text-xl font-black text-emerald-600 mt-1">₹{{ number_format($data['summary']['total_profit'], 2) }}</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">COGS: ₹{{ number_format($data['summary']['total_cost'], 2) }}</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Average Margin %</span>
            <div class="text-xl font-black text-neutral-900 mt-1">{{ $data['summary']['avg_margin'] }}%</div>
            <div class="text-[11px] text-emerald-600 font-bold mt-0.5">Blended Margin</div>
        </div>
    </div>

    <!-- Bill-wise Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-neutral-800">Invoice Profitability Schedule</h3>
            <span class="text-xs text-neutral-500 font-mono">{{ count($data['rows']) }} invoices listed</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-neutral-50/75 border-b border-neutral-200/80 text-[11px] font-bold text-neutral-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Invoice No. & Date</th>
                        <th class="py-3 px-4">Customer Name</th>
                        <th class="py-3 px-4">Consignment / LR</th>
                        <th class="py-3 px-4 text-right">Revenue (₹)</th>
                        <th class="py-3 px-4 text-right">Cost (COGS) (₹)</th>
                        <th class="py-3 px-4 text-right">Gross Profit (₹)</th>
                        <th class="py-3 px-4 text-center">Margin %</th>
                        <th class="py-3 px-4 text-center">Health</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 font-medium">
                    @forelse($data['rows'] as $row)
                    <tr class="hover:bg-neutral-50/60 transition-colors">
                        <td class="py-3 px-4">
                            <a href="{{ route('invoices.pdf', $row['id']) }}" target="_blank" class="font-bold text-neutral-900 hover:underline">
                                {{ $row['invoice_number'] }}
                            </a>
                            <div class="text-[10px] text-neutral-400">
                                {{ \Carbon\Carbon::parse($row['invoice_date'])->format('d M Y') }}
                            </div>
                        </td>
                        <td class="py-3 px-4 text-neutral-700">
                            {{ $row['customer_name'] }}
                        </td>
                        <td class="py-3 px-4 text-neutral-500">
                            <div>{{ $row['lr_number'] }}</div>
                            @if($row['eway_bill_number'])
                                <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] bg-purple-50 text-purple-700 font-mono">
                                    EWB: {{ $row['eway_bill_number'] }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-neutral-800">
                            ₹{{ number_format($row['revenue'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-neutral-600">
                            ₹{{ number_format($row['cost'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold {{ $row['gross_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            ₹{{ number_format($row['gross_profit'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-center font-mono font-bold text-neutral-900">
                            {{ $row['margin_percentage'] }}%
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($row['health'] === 'HIGH')
                                <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md text-[10px] font-bold">HIGH</span>
                            @elseif($row['health'] === 'HEALTHY')
                                <span class="px-2 py-0.5 bg-blue-50 border border-blue-200 text-blue-800 rounded-md text-[10px] font-bold">HEALTHY</span>
                            @elseif($row['health'] === 'LOW')
                                <span class="px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-md text-[10px] font-bold">LOW</span>
                            @else
                                <span class="px-2 py-0.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-md text-[10px] font-bold">NEGATIVE</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-neutral-400">
                            No invoices found matching the specified filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
