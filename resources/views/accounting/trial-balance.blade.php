@extends('layouts.app')

@section('title', 'Trial Balance')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Accounting Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('accounting.chart') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📚 Chart of Accounts
            </a>
            <a href="{{ route('accounting.vouchers') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📝 Journal Vouchers
            </a>
            <a href="{{ route('accounting.trial-balance') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                ⚖️ Trial Balance
            </a>
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🧾 Expenses & ITC
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏭 Fixed Assets
            </a>
        </div>
        <button onclick="window.print()" class="px-3 py-1.5 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 text-xs font-bold transition-colors">
            🖨️ Print Trial Balance
        </button>
    </div>

    <!-- Balance Status Banner -->
    @if($trialBalance['is_balanced'])
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">✓</span>
                <div>
                    <h4 class="font-bold text-sm text-emerald-900">General Ledger is in Perfect Balance</h4>
                    <p class="text-xs text-emerald-700">Total Debits equal Total Credits (₹{{ number_format($trialBalance['grand_debit'], 2) }}).</p>
                </div>
            </div>
            <span class="font-mono text-xs font-bold text-emerald-800 bg-white/80 px-3 py-1.5 rounded-xl border border-emerald-200">
                Discrepancy: ₹0.00
            </span>
        </div>
    @else
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-sm">✕</span>
                <div>
                    <h4 class="font-bold text-sm text-rose-900">Trial Balance Out of Balance</h4>
                    <p class="text-xs text-rose-700">Discrepancy of ₹{{ number_format(abs($trialBalance['grand_debit'] - $trialBalance['grand_credit']), 2) }} detected between Debits and Credits.</p>
                </div>
            </div>
            <span class="font-mono text-xs font-bold text-rose-800 bg-white/80 px-3 py-1.5 rounded-xl border border-rose-200">
                Diff: ₹{{ number_format(abs($trialBalance['grand_debit'] - $trialBalance['grand_credit']), 2) }}
            </span>
        </div>
    @endif

    <!-- Trial Balance Sheet Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between">
            <div>
                <h3 class="font-black text-neutral-900 text-base">Trial Balance Statement</h3>
                <p class="text-xs text-neutral-500">As of {{ now()->format('d M Y, h:i A') }}</p>
            </div>
            <div class="font-mono text-xs text-right">
                <span class="text-neutral-500">Total Ledger Accounts:</span>
                <span class="font-bold text-neutral-900 ml-1">
                    {{ array_sum(array_map(fn($g) => count($g['accounts']), $trialBalance['groups'])) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold w-24">Code</th>
                        <th class="py-3 px-4 font-bold">Account Description</th>
                        <th class="py-3 px-4 font-bold w-48">Classification</th>
                        <th class="py-3 px-4 font-bold text-right w-44">Debit Balance (₹)</th>
                        <th class="py-3 px-4 font-bold text-right w-44">Credit Balance (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach($trialBalance['groups'] as $groupName => $groupData)
                        @if(count($groupData['accounts']) > 0)
                            <tr class="bg-neutral-100/60 font-bold">
                                <td colspan="3" class="py-2.5 px-4 uppercase tracking-wider text-[11px] text-neutral-800">
                                    {{ $groupName }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-neutral-700">₹{{ number_format($groupData['total_debit'], 2) }}</td>
                                <td class="py-2.5 px-4 text-right font-mono text-neutral-700">₹{{ number_format($groupData['total_credit'], 2) }}</td>
                            </tr>
                            @foreach($groupData['accounts'] as $acc)
                                <tr class="hover:bg-neutral-50/60 transition-colors">
                                    <td class="py-2.5 px-4 font-mono font-bold text-neutral-800">{{ $acc['code'] }}</td>
                                    <td class="py-2.5 px-4 text-neutral-900 font-medium">
                                        <a href="{{ route('accounting.ledger', $acc['id']) }}" class="hover:underline">
                                            {{ $acc['name'] }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-4 text-neutral-500 text-[11px]">
                                        <span class="px-2 py-0.5 rounded-md bg-neutral-100 font-mono">{{ $acc['sub_group'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-4 font-mono text-right {{ $acc['debit'] > 0 ? 'font-bold text-neutral-900' : 'text-neutral-400' }}">
                                        {{ $acc['debit'] > 0 ? '₹' . number_format($acc['debit'], 2) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-4 font-mono text-right {{ $acc['credit'] > 0 ? 'font-bold text-neutral-900' : 'text-neutral-400' }}">
                                        {{ $acc['credit'] > 0 ? '₹' . number_format($acc['credit'], 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
                <tfoot class="bg-neutral-900 text-white font-bold font-mono">
                    <tr>
                        <td colspan="3" class="py-3.5 px-4 uppercase tracking-wider text-xs">
                            Grand Totals
                        </td>
                        <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">
                            ₹{{ number_format($trialBalance['grand_debit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">
                            ₹{{ number_format($trialBalance['grand_credit'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
