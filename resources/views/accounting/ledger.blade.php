@extends('layouts.app')

@section('title', 'Ledger Statement - ' . $account->account_name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Sub-Nav & Back button -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.chart') }}" class="p-2 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-neutral-500">[{{ $account->account_code }}]</span>
                    <h2 class="text-base font-black text-neutral-900">{{ $account->account_name }}</h2>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-700">{{ $account->account_group }}</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-500 font-mono">{{ $account->sub_group }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3 py-1.5 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 text-xs font-bold transition-colors">
                🖨️ Print
            </button>
        </div>
    </div>

    <!-- Date Filter Form -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs">
        <form method="GET" action="{{ route('accounting.ledger', $account->id) }}" class="flex flex-wrap items-end gap-3 text-xs">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
            </div>
            <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl font-bold transition-colors">
                Apply Date Range
            </button>
            <a href="{{ route('accounting.ledger', $account->id) }}" class="px-3 py-2 border border-neutral-200 text-neutral-600 hover:bg-neutral-100 rounded-xl font-bold transition-colors">
                Reset
            </a>
        </form>
    </div>

    <!-- Ledger Balances Summary Bento -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Opening Balance</span>
            <div class="text-lg font-black text-neutral-800 mt-1">₹{{ number_format($openingBalance, 2) }}</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Debit Movements</span>
            <div class="text-lg font-black text-blue-600 mt-1">₹{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Credit Movements</span>
            <div class="text-lg font-black text-amber-600 mt-1">₹{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Closing Balance</span>
            <div class="text-lg font-black {{ $closingBalance >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">
                ₹{{ number_format($closingBalance, 2) }}
            </div>
        </div>
    </div>

    <!-- Ledger Transactions Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Date</th>
                        <th class="py-3 px-4 font-bold">Voucher No</th>
                        <th class="py-3 px-4 font-bold">Type</th>
                        <th class="py-3 px-4 font-bold">Particulars / Narration</th>
                        <th class="py-3 px-4 font-bold text-right">Debit (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Credit (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Running Balance (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr class="bg-neutral-50/40 font-bold">
                        <td colspan="4" class="py-2.5 px-4 text-neutral-700">Opening Balance Forward</td>
                        <td class="py-2.5 px-4 text-right font-mono">—</td>
                        <td class="py-2.5 px-4 text-right font-mono">—</td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold text-neutral-900">₹{{ number_format($openingBalance, 2) }}</td>
                    </tr>
                    @forelse($transactions as $t)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $t['date'] }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-neutral-900">{{ $t['voucher_number'] }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-800">
                                    {{ $t['voucher_type'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-neutral-800">
                                <div>{{ $t['narration'] ?: '—' }}</div>
                                @if($t['remarks'])
                                    <div class="text-[11px] text-neutral-500 italic mt-0.5">{{ $t['remarks'] }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-right {{ $t['debit'] > 0 ? 'font-bold text-neutral-900' : 'text-neutral-400' }}">
                                {{ $t['debit'] > 0 ? '₹' . number_format($t['debit'], 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-right {{ $t['credit'] > 0 ? 'font-bold text-neutral-900' : 'text-neutral-400' }}">
                                {{ $t['credit'] > 0 ? '₹' . number_format($t['credit'], 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-right {{ $t['balance'] >= 0 ? 'text-neutral-900' : 'text-rose-600' }}">
                                ₹{{ number_format($t['balance'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-neutral-400">No voucher transactions recorded for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-neutral-50/80 border-t border-neutral-200 font-bold font-mono">
                    <tr>
                        <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-neutral-600">Total Period Movements:</td>
                        <td class="py-3 px-4 text-right font-bold text-blue-600">₹{{ number_format($totalDebit, 2) }}</td>
                        <td class="py-3 px-4 text-right font-bold text-amber-600">₹{{ number_format($totalCredit, 2) }}</td>
                        <td class="py-3 px-4 text-right font-black {{ $closingBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            ₹{{ number_format($closingBalance, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
