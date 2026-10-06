@extends('layouts.app')

@section('title', 'Accounts Receivable (AR) Aging Analysis')

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
            <a href="{{ route('reports.party-profit') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                👥 Party Profitability
            </a>
            <a href="{{ route('reports.ar-aging') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                ⏳ AR Aging
            </a>
            <a href="{{ route('reports.batch-trace') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🔍 Batch Traceability
            </a>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('reports.ar-aging.pdf', request()->query()) }}"
               class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📄</span> Export PDF
            </a>
            <a href="{{ route('reports.ar-aging.excel', request()->query()) }}"
               class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📊</span> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('reports.ar-aging') }}" class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">As of Date</label>
                <input type="date" name="as_of_date" value="{{ \Carbon\Carbon::parse($data['as_of_date'])->format('Y-m-d') }}"
                       class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">Customer</label>
                <select name="customer_id" class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden max-w-[220px]">
                    <option value="">All Debtors</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>
                            {{ $c->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="self-end">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Recalculate Aging
                </button>
            </div>
        </div>

        <div class="text-xs text-neutral-500 font-medium">
            Overdue Invoices: <span class="font-bold text-neutral-800">{{ $data['summary']['invoices_count'] }} pending</span>
        </div>
    </form>

    <!-- Aging Interval Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Total Outstanding</span>
            <div class="text-lg font-black text-neutral-900 mt-1">₹{{ number_format($data['summary']['total_outstanding'], 2) }}</div>
            <div class="text-[10px] text-neutral-500 mt-0.5">Total Receivables</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Current (Not Due)</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format($data['summary']['current_total'], 2) }}</div>
            <div class="text-[10px] text-neutral-500 mt-0.5">Within credit terms</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">1 – 30 Days</span>
            <div class="text-lg font-black text-blue-600 mt-1">₹{{ number_format($data['summary']['days_0_30_total'], 2) }}</div>
            <div class="text-[10px] text-neutral-500 mt-0.5">Recent overdue</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">31 – 60 Days</span>
            <div class="text-lg font-black text-amber-600 mt-1">₹{{ number_format($data['summary']['days_31_60_total'], 2) }}</div>
            <div class="text-[10px] text-neutral-500 mt-0.5">Moderate risk</div>
        </div>

        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-orange-600">61 – 90 Days</span>
            <div class="text-lg font-black text-orange-600 mt-1">₹{{ number_format($data['summary']['days_61_90_total'], 2) }}</div>
            <div class="text-[10px] text-neutral-500 mt-0.5">Follow-up needed</div>
        </div>

        <div class="bg-white border border-rose-200 bg-rose-50/20 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">90+ Days Critical</span>
            <div class="text-lg font-black text-rose-600 mt-1">₹{{ number_format($data['summary']['days_90_plus_total'], 2) }}</div>
            <div class="text-[10px] text-rose-500 mt-0.5">High recovery alert</div>
        </div>
    </div>

    <!-- Customer-Wise Aging Schedule -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-neutral-800">Debtor Account Aging Breakdown</h3>
            <span class="text-xs text-neutral-500 font-mono">{{ count($data['customers']) }} debtors with open balances</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-neutral-50/75 border-b border-neutral-200/80 text-[11px] font-bold text-neutral-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Customer Account</th>
                        <th class="py-3 px-4 text-right">Total Balance (₹)</th>
                        <th class="py-3 px-4 text-right text-emerald-600">Current (₹)</th>
                        <th class="py-3 px-4 text-right text-blue-600">1–30 Days (₹)</th>
                        <th class="py-3 px-4 text-right text-amber-600">31–60 Days (₹)</th>
                        <th class="py-3 px-4 text-right text-orange-600">61–90 Days (₹)</th>
                        <th class="py-3 px-4 text-right text-rose-600">90+ Days (₹)</th>
                        <th class="py-3 px-4 text-center">Invoices</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 font-medium">
                    @forelse($data['customers'] as $cust)
                    <tr class="hover:bg-neutral-50/60 transition-colors">
                        <td class="py-3 px-4">
                            <a href="{{ route('customers.show', $cust['customer_id']) }}" class="font-bold text-neutral-900 hover:underline">
                                {{ $cust['customer_name'] }}
                            </a>
                            @if($cust['customer_phone'])
                                <div class="text-[10px] text-neutral-400 font-mono">{{ $cust['customer_phone'] }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-black text-neutral-900">
                            ₹{{ number_format($cust['total_balance'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono {{ $cust['current'] > 0 ? 'text-emerald-700 font-bold' : 'text-neutral-300' }}">
                            {{ $cust['current'] > 0 ? '₹'.number_format($cust['current'], 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono {{ $cust['days_0_30'] > 0 ? 'text-blue-700 font-bold' : 'text-neutral-300' }}">
                            {{ $cust['days_0_30'] > 0 ? '₹'.number_format($cust['days_0_30'], 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono {{ $cust['days_31_60'] > 0 ? 'text-amber-700 font-bold' : 'text-neutral-300' }}">
                            {{ $cust['days_31_60'] > 0 ? '₹'.number_format($cust['days_31_60'], 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono {{ $cust['days_61_90'] > 0 ? 'text-orange-700 font-bold' : 'text-neutral-300' }}">
                            {{ $cust['days_61_90'] > 0 ? '₹'.number_format($cust['days_61_90'], 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono {{ $cust['days_90_plus'] > 0 ? 'text-rose-700 font-black bg-rose-50/50' : 'text-neutral-300' }}">
                            {{ $cust['days_90_plus'] > 0 ? '₹'.number_format($cust['days_90_plus'], 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-neutral-700">
                            {{ $cust['invoices_count'] }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($cust['customer_phone'])
                                @php
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $cust['customer_phone']);
                                    if (strlen($cleanPhone) === 10) $cleanPhone = '91'.$cleanPhone;
                                    $waMsg = urlencode("Dear ".$cust['customer_name'].", friendly reminder that outstanding balance of ₹".number_format($cust['total_balance'], 2)." is pending with us. Please arrange payment at your earliest convenience.");
                                @endphp
                                <a href="https://wa.me/{{ $cleanPhone }}?text={{ $waMsg }}" target="_blank"
                                   title="Send WhatsApp Payment Reminder"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[10px] font-bold transition-colors">
                                    <span>💬</span> Remind
                                </a>
                            @else
                                <span class="text-[10px] text-neutral-300">No phone</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-neutral-400">
                            Zero outstanding accounts receivable found as of {{ \Carbon\Carbon::parse($data['as_of_date'])->format('d M Y') }}.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
