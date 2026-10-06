@extends('layouts.app')

@section('title', 'Journal Vouchers')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddVoucherModal: false,
    selectedVoucher: null,
    lines: [
        { account_id: '', debit_amount: '', credit_amount: '', remarks: '' },
        { account_id: '', debit_amount: '', credit_amount: '', remarks: '' }
    ],
    addRow() {
        this.lines.push({ account_id: '', debit_amount: '', credit_amount: '', remarks: '' });
    },
    removeRow(index) {
        if (this.lines.length > 2) {
            this.lines.splice(index, 1);
        }
    },
    get totalDebit() {
        return this.lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0);
    },
    get totalCredit() {
        return this.lines.reduce((sum, line) => sum + (parseFloat(line.credit_amount) || 0), 0);
    },
    get discrepancy() {
        return Math.abs(this.totalDebit - this.totalCredit);
    },
    get isBalanced() {
        return this.discrepancy < 0.01 && this.totalDebit > 0;
    }
}">
    <!-- Accounting Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('accounting.chart') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📚 Chart of Accounts
            </a>
            <a href="{{ route('accounting.vouchers') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                📝 Journal Vouchers
            </a>
            <a href="{{ route('accounting.trial-balance') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                ⚖️ Trial Balance
            </a>
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🧾 Expenses & ITC
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏭 Fixed Assets
            </a>
        </div>
        <button @click="showAddVoucherModal = true" class="px-4 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
            <span>+</span> New Journal Voucher
        </button>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs">
        <form method="GET" action="{{ route('accounting.vouchers') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Voucher Type</label>
                <select name="type" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="">All Types</option>
                    @foreach(['JOURNAL', 'SALES', 'PURCHASE', 'PAYMENT', 'RECEIPT', 'CONTRA', 'DEPRECIATION'] as $t)
                        <option value="{{ $t }}" {{ $voucherType === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl font-bold transition-colors w-full">Filter</button>
                <a href="{{ route('accounting.vouchers') }}" class="px-3 py-2 border border-neutral-200 text-neutral-600 hover:bg-neutral-100 rounded-xl font-bold transition-colors">Reset</a>
            </div>
        </form>
    </div>

    <!-- Vouchers Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Voucher No</th>
                        <th class="py-3 px-4 font-bold">Date</th>
                        <th class="py-3 px-4 font-bold">Type</th>
                        <th class="py-3 px-4 font-bold">Narration</th>
                        <th class="py-3 px-4 font-bold">Reference</th>
                        <th class="py-3 px-4 font-bold text-right">Debit (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Credit (₹)</th>
                        <th class="py-3 px-4 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($vouchers as $v)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-neutral-900">{{ $v->voucher_number }}</td>
                            <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $v->voucher_date->format('d M Y') }}</td>
                            <td class="py-3 px-4">
                                @php
                                    $badgeStyles = [
                                        'JOURNAL' => 'bg-neutral-100 text-neutral-800',
                                        'SALES' => 'bg-blue-50 text-blue-700',
                                        'PURCHASE' => 'bg-amber-50 text-amber-700',
                                        'PAYMENT' => 'bg-rose-50 text-rose-700',
                                        'RECEIPT' => 'bg-emerald-50 text-emerald-700',
                                        'CONTRA' => 'bg-purple-50 text-purple-700',
                                        'DEPRECIATION' => 'bg-indigo-50 text-indigo-700',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $badgeStyles[$v->voucher_type] ?? 'bg-neutral-100' }}">
                                    {{ $v->voucher_type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-neutral-700 max-w-xs truncate" title="{{ $v->narration }}">
                                {{ $v->narration ?: '—' }}
                            </td>
                            <td class="py-3 px-4 text-neutral-500 font-mono text-[11px]">
                                {{ $v->reference_type ? "{$v->reference_type} #{$v->reference_id}" : 'Manual' }}
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-neutral-900 text-right">₹{{ number_format((float)$v->total_debit, 2) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-neutral-900 text-right">₹{{ number_format((float)$v->total_credit, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($v->isBalanced())
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        Balanced
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full">
                                        Unbalanced
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-neutral-400">No journal vouchers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vouchers->hasPages())
            <div class="px-5 py-3 border-t border-neutral-100">
                {{ $vouchers->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: New Manual Journal Voucher with Live Double-Entry Balancing -->
    <div x-show="showAddVoucherModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAddVoucherModal = false" class="bg-white rounded-2xl max-w-4xl w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div>
                    <h3 class="font-black text-neutral-900 text-base">Create Double-Entry Journal Voucher</h3>
                    <p class="text-xs text-neutral-500">Every voucher strictly enforces total debit equaling total credit.</p>
                </div>
                <button @click="showAddVoucherModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('accounting.vouchers.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Voucher Date *</label>
                        <input type="date" name="voucher_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Voucher Type *</label>
                        <select name="voucher_type" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="JOURNAL">JOURNAL (Standard Double Entry)</option>
                            <option value="CONTRA">CONTRA (Cash / Bank Transfer)</option>
                            <option value="PAYMENT">PAYMENT</option>
                            <option value="RECEIPT">RECEIPT</option>
                            <option value="PURCHASE">PURCHASE</option>
                            <option value="SALES">SALES</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Narration / Remarks</label>
                        <input type="text" name="narration" placeholder="e.g. Office rent settlement for March" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    </div>
                </div>

                <!-- Lines Table -->
                <div class="border border-neutral-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-2.5 px-3 font-bold w-5/12">Account Ledger *</th>
                                <th class="py-2.5 px-3 font-bold text-right w-2/12">Debit Amount (₹)</th>
                                <th class="py-2.5 px-3 font-bold text-right w-2/12">Credit Amount (₹)</th>
                                <th class="py-2.5 px-3 font-bold w-3/12">Line Remarks</th>
                                <th class="py-2.5 px-2 text-center w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td class="p-2">
                                        <select :name="'items[' + index + '][account_id]'" x-model="line.account_id" required class="w-full px-2 py-1.5 text-xs rounded-lg border border-neutral-200 focus:outline-none focus:border-neutral-900">
                                            <option value="">Select Account...</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->account_name }} ({{ $acc->account_group }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" :name="'items[' + index + '][debit_amount]'" x-model="line.debit_amount" placeholder="0.00"
                                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-neutral-200 text-right font-mono focus:outline-none focus:border-neutral-900">
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" :name="'items[' + index + '][credit_amount]'" x-model="line.credit_amount" placeholder="0.00"
                                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-neutral-200 text-right font-mono focus:outline-none focus:border-neutral-900">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" :name="'items[' + index + '][remarks]'" x-model="line.remarks" placeholder="Optional notes"
                                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-neutral-200 focus:outline-none focus:border-neutral-900">
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" @click="removeRow(index)" class="text-rose-500 hover:text-rose-700 font-bold text-sm" title="Remove line">&times;</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-neutral-50/70 border-t border-neutral-200 font-bold font-mono">
                            <tr>
                                <td class="p-2.5">
                                    <button type="button" @click="addRow()" class="px-3 py-1 bg-white border border-neutral-200 hover:bg-neutral-100 rounded-lg text-neutral-700 text-xs font-semibold">
                                        + Add Line
                                    </button>
                                </td>
                                <td class="p-2.5 text-right font-bold text-neutral-900" x-text="'₹' + totalDebit.toFixed(2)"></td>
                                <td class="p-2.5 text-right font-bold text-neutral-900" x-text="'₹' + totalCredit.toFixed(2)"></td>
                                <td colspan="2" class="p-2.5 text-right">
                                    <template x-if="isBalanced">
                                        <span class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md text-xs font-bold">
                                            ✓ Balanced
                                        </span>
                                    </template>
                                    <template x-if="!isBalanced">
                                        <span class="inline-flex items-center gap-1 text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md text-xs font-bold"
                                              x-text="'Discrepancy: ₹' + discrepancy.toFixed(2)"></span>
                                    </template>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-neutral-100">
                    <span class="text-xs text-neutral-500" x-show="!isBalanced">
                        ⚠️ Voucher cannot be posted until total debits equal total credits.
                    </span>
                    <span class="text-xs text-emerald-600 font-bold" x-show="isBalanced">
                        ✓ Ready to post to General Ledger.
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showAddVoucherModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                        <button type="submit" :disabled="!isBalanced" :class="isBalanced ? 'bg-neutral-900 hover:bg-neutral-800 text-white cursor-pointer' : 'bg-neutral-300 text-neutral-500 cursor-not-allowed'" class="px-5 py-2 text-xs font-bold rounded-xl shadow-xs transition-colors">
                            Post Voucher
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
