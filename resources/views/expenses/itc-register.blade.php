@extends('layouts.app')

@section('title', 'Input Tax Credit (ITC) Register - GSTR-3B')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('accounting.chart') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📚 Chart of Accounts
            </a>
            <a href="{{ route('accounting.vouchers') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📝 Journal Vouchers
            </a>
            <a href="{{ route('accounting.trial-balance') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                ⚖️ Trial Balance
            </a>
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🧾 Expenses
            </a>
            <a href="{{ route('expenses.itc-register') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                📊 ITC Register (GSTR-3B)
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏭 Fixed Assets
            </a>
        </div>
        <button onclick="window.print()" class="px-3 py-1.5 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 text-xs font-bold transition-colors">
            🖨️ Print ITC Register
        </button>
    </div>

    <!-- Month Selector Form -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs flex items-center justify-between">
        <form method="GET" action="{{ route('expenses.itc-register') }}" class="flex items-center gap-3 text-xs">
            <label class="font-bold text-neutral-700">Tax Return Period (Month):</label>
            <input type="month" name="month" value="{{ $month }}" class="px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
            <button type="submit" class="px-4 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl font-bold transition-colors">
                Load Period
            </button>
        </form>
        <span class="text-xs text-neutral-500 font-mono">Total ITC Invoices: {{ count($expenses) }}</span>
    </div>

    <!-- GSTR-3B Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Eligible Input CGST</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format((float)$summary['eligible_cgst'], 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Table 4(A)(5)</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Eligible Input SGST</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format((float)$summary['eligible_sgst'], 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Table 4(A)(5)</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Eligible Input IGST</span>
            <div class="text-lg font-black text-blue-600 mt-1">₹{{ number_format((float)$summary['eligible_igst'], 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Table 4(A)(5) Interstate</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Total Net Available ITC</span>
            <div class="text-lg font-black text-neutral-900 mt-1">₹{{ number_format((float)$summary['total_available_itc'], 2) }}</div>
            <div class="text-[10px] text-emerald-600 font-bold mt-0.5">Offset against GSTR-3B</div>
        </div>
    </div>

    <!-- Section 1: Eligible ITC for GSTR-3B Table 4(A) -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="px-5 py-3 border-b border-neutral-100 bg-emerald-50/30 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-black text-sm uppercase tracking-wider text-emerald-950">Table 4(A) Eligible Input Tax Credit</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-100 text-emerald-800">
                    {{ count($expenses->where('is_itc_eligible', true)) }} Invoices
                </span>
            </div>
            <span class="font-mono font-bold text-xs text-emerald-800">Available: ₹{{ number_format((float)$summary['total_available_itc'], 2) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Date</th>
                        <th class="py-3 px-4 font-bold">Vendor & GSTIN</th>
                        <th class="py-3 px-4 font-bold">Invoice Ref</th>
                        <th class="py-3 px-4 font-bold">ITC Classification</th>
                        <th class="py-3 px-4 font-bold text-right">Taxable (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">CGST (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">SGST (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">IGST (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Total ITC (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($expenses->where('is_itc_eligible', true) as $e)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 text-neutral-900">
                                <div class="font-bold">{{ $e->vendor?->company_name ?: 'Direct Expense' }}</div>
                                @if($e->vendor?->gstin)
                                    <div class="text-[10px] font-mono text-neutral-500">{{ $e->vendor->gstin }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-neutral-700 text-[11px]">{{ $e->vendor_invoice_number ?: '—' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-700 font-mono">
                                    {{ $e->itc_type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-right text-neutral-700">₹{{ number_format((float)$e->base_amount, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-right text-neutral-700">{{ (float)$e->cgst_amount > 0 ? '₹' . number_format((float)$e->cgst_amount, 2) : '—' }}</td>
                            <td class="py-3 px-4 font-mono text-right text-neutral-700">{{ (float)$e->sgst_amount > 0 ? '₹' . number_format((float)$e->sgst_amount, 2) : '—' }}</td>
                            <td class="py-3 px-4 font-mono text-right text-neutral-700">{{ (float)$e->igst_amount > 0 ? '₹' . number_format((float)$e->igst_amount, 2) : '—' }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-right text-emerald-700">
                                ₹{{ number_format((float)($e->cgst_amount + $e->sgst_amount + $e->igst_amount), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-neutral-400">No eligible ITC records found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Ineligible ITC under Section 17(5) -->
    @if(count($ineligibleExpenses) > 0)
        <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-100 bg-rose-50/30 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-black text-sm uppercase tracking-wider text-rose-950">Table 4(D)(1) Ineligible ITC under Section 17(5)</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-100 text-rose-800">
                        {{ count($ineligibleExpenses) }} Invoices
                    </span>
                </div>
                <span class="font-mono font-bold text-xs text-rose-800">Ineligible: ₹{{ number_format((float)$summary['ineligible_tax'], 2) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="py-3 px-4 font-bold">Date</th>
                            <th class="py-3 px-4 font-bold">Vendor</th>
                            <th class="py-3 px-4 font-bold">Category</th>
                            <th class="py-3 px-4 font-bold text-right">Taxable (₹)</th>
                            <th class="py-3 px-4 font-bold text-right">Non-Claimable Tax (₹)</th>
                            <th class="py-3 px-4 font-bold text-right">Total Expensed (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach($ineligibleExpenses as $e)
                            <tr class="hover:bg-neutral-50/60 transition-colors">
                                <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                                <td class="py-3 px-4 text-neutral-900 font-medium">{{ $e->vendor?->company_name ?: 'Direct Expense' }}</td>
                                <td class="py-3 px-4 text-neutral-700">{{ $e->expense_category }}</td>
                                <td class="py-3 px-4 font-mono text-right text-neutral-700">₹{{ number_format((float)$e->base_amount, 2) }}</td>
                                <td class="py-3 px-4 font-mono text-right text-rose-600 font-bold">₹{{ number_format((float)($e->cgst_amount + $e->sgst_amount + $e->igst_amount), 2) }}</td>
                                <td class="py-3 px-4 font-mono text-right text-neutral-900 font-bold">₹{{ number_format((float)$e->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
