@extends('layouts.app')

@section('title', 'Expenses & Input Tax Credit')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddExpenseModal: false,
    baseAmount: '',
    gstRate: '18',
    isItcEligible: true,
    itcType: 'INPUT_SERVICES',
    paymentStatus: 'PAID',
    get computedGst() {
        let b = parseFloat(this.baseAmount) || 0;
        let r = parseFloat(this.gstRate) || 0;
        return (b * (r / 100));
    },
    get computedTotal() {
        let b = parseFloat(this.baseAmount) || 0;
        return b + this.computedGst;
    }
}">
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
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                🧾 Expenses
            </a>
            <a href="{{ route('expenses.itc-register') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📊 ITC Register (GSTR-3B)
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🏭 Fixed Assets
            </a>
        </div>
        <button @click="showAddExpenseModal = true" class="px-4 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
            <span>+</span> Record Expense
        </button>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Expenses</span>
            <div class="text-lg font-black text-neutral-900 mt-1">₹{{ number_format($totalExpense, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Gross spent</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Taxable Base</span>
            <div class="text-lg font-black text-neutral-700 mt-1">₹{{ number_format($totalBase, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Before GST</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">GST Paid</span>
            <div class="text-lg font-black text-blue-600 mt-1">₹{{ number_format($totalGstPaid, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">CGST + SGST + IGST</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Claimable ITC</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format($eligibleItc, 2) }}</div>
            <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">Eligible for GSTR-3B</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Pending Payables</span>
            <div class="text-lg font-black text-amber-600 mt-1">₹{{ number_format($pendingPayouts, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Unpaid vendor bills</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs">
        <form method="GET" action="{{ route('expenses.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Category</label>
                <select name="category" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Payment Status</label>
                <select name="payment_status" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="">All Statuses</option>
                    <option value="PAID" {{ $paymentStatus === 'PAID' ? 'selected' : '' }}>PAID</option>
                    <option value="PENDING" {{ $paymentStatus === 'PENDING' ? 'selected' : '' }}>PENDING</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">ITC Claim</label>
                <select name="itc_eligible" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="">All</option>
                    <option value="1" {{ $itcEligible === '1' ? 'selected' : '' }}>Eligible</option>
                    <option value="0" {{ $itcEligible === '0' ? 'selected' : '' }}>Ineligible</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Date Range</label>
                <div class="flex items-center gap-1">
                    <input type="date" name="from_date" value="{{ $fromDate }}" class="w-1/2 px-2 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 text-[11px]">
                    <input type="date" name="to_date" value="{{ $toDate }}" class="w-1/2 px-2 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 text-[11px]">
                </div>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl font-bold transition-colors w-full">Filter</button>
                <a href="{{ route('expenses.index') }}" class="px-3 py-2 border border-neutral-200 text-neutral-600 hover:bg-neutral-100 rounded-xl font-bold transition-colors">Reset</a>
            </div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Date</th>
                        <th class="py-3 px-4 font-bold">Category</th>
                        <th class="py-3 px-4 font-bold">Vendor / Supplier</th>
                        <th class="py-3 px-4 font-bold">Invoice Ref</th>
                        <th class="py-3 px-4 font-bold text-right">Base Amount (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">GST Split (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Total (₹)</th>
                        <th class="py-3 px-4 font-bold text-center">ITC Eligibility</th>
                        <th class="py-3 px-4 font-bold text-center">Payment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($expenses as $e)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 font-bold text-neutral-900">{{ $e->expense_category }}</td>
                            <td class="py-3 px-4 text-neutral-700">
                                {{ $e->vendor?->company_name ?: 'Direct Expense' }}
                            </td>
                            <td class="py-3 px-4 text-neutral-500 font-mono text-[11px]">{{ $e->vendor_invoice_number ?: '—' }}</td>
                            <td class="py-3 px-4 font-mono text-right text-neutral-700">₹{{ number_format((float)$e->base_amount, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-right text-[11px]">
                                @if((float)$e->igst_amount > 0)
                                    <span class="text-blue-600 font-bold">IGST: ₹{{ number_format((float)$e->igst_amount, 2) }}</span>
                                @elseif((float)$e->cgst_amount > 0 || (float)$e->sgst_amount > 0)
                                    <span class="text-neutral-600">C: ₹{{ number_format((float)$e->cgst_amount, 2) }}</span>
                                    <span class="text-neutral-400">|</span>
                                    <span class="text-neutral-600">S: ₹{{ number_format((float)$e->sgst_amount, 2) }}</span>
                                @else
                                    <span class="text-neutral-400">0%</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-right text-neutral-900">₹{{ number_format((float)$e->total_amount, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($e->is_itc_eligible)
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full" title="{{ $e->itc_type }}">
                                        ✓ ITC Eligible
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded-full" title="Ineligible under Sec 17(5)">
                                        ✕ Ineligible
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if(strtoupper($e->payment_status) === 'PAID')
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-700">PAID</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-amber-50 text-amber-700">PENDING</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-neutral-400">No expenses recorded matching the criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
            <div class="px-5 py-3 border-t border-neutral-100">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Record Expense -->
    <div x-show="showAddExpenseModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAddExpenseModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Record Business Expense & ITC</h3>
                <button @click="showAddExpenseModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('expenses.store') }}" class="space-y-3.5">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Expense Date *</label>
                        <input type="date" name="expense_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Category *</label>
                        <input type="text" name="expense_category" required list="expense-categories" placeholder="e.g. Freight Inward" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                        <datalist id="expense-categories">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Vendor / Payee</label>
                        <select name="vendor_id" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="">Direct Expense (No Vendor)</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->company_name }} ({{ $v->state ?: 'Gujarat' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Vendor Invoice / Ref No</label>
                        <input type="text" name="vendor_invoice_number" placeholder="e.g. BILL-9821" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Base Amount (₹) *</label>
                        <input type="number" step="0.01" name="base_amount" required x-model="baseAmount" placeholder="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">GST Rate (%) *</label>
                        <select name="gst_rate" x-model="gstRate" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="0">0% (Nil / Exempt)</option>
                            <option value="5">5% (GST)</option>
                            <option value="12">12% (GST)</option>
                            <option value="18">18% (Standard GST)</option>
                            <option value="28">28% (Luxury / De-merit)</option>
                        </select>
                    </div>
                </div>

                <!-- Live Auto-Calculation Preview -->
                <div class="p-3 rounded-xl bg-neutral-50 border border-neutral-200/80 flex items-center justify-between text-xs font-mono">
                    <span class="text-neutral-500">Tax: <span class="font-bold text-neutral-800" x-text="'₹' + computedGst.toFixed(2)"></span></span>
                    <span class="text-neutral-900 font-black text-sm">Total: <span x-text="'₹' + computedTotal.toFixed(2)"></span></span>
                </div>

                <!-- ITC Claim Controls -->
                <div class="p-3 rounded-xl bg-neutral-50/60 border border-neutral-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-neutral-800 flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_itc_eligible" value="1" x-model="isItcEligible" class="rounded text-neutral-900">
                            <span>Eligible for Input Tax Credit (ITC)</span>
                        </label>
                    </div>

                    <div x-show="isItcEligible" class="pt-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">ITC Classification *</label>
                        <select name="itc_type" x-model="itcType" class="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="INPUT_SERVICES">Input Services (Table 4A - 5)</option>
                            <option value="INPUT_GOODS">Input Goods (Raw Material & Consumables)</option>
                            <option value="CAPITAL_GOODS">Capital Goods (Machinery & Assets)</option>
                        </select>
                    </div>

                    <div x-show="!isItcEligible" class="pt-2">
                        <input type="hidden" name="itc_type" value="INELIGIBLE" :disabled="isItcEligible">
                        <p class="text-[11px] text-amber-700">⚠️ Ineligible under Section 17(5) (Blocked Credit). Full GST amount will be capitalized as expense.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Payment Status *</label>
                        <select name="payment_status" x-model="paymentStatus" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="PAID">PAID</option>
                            <option value="PENDING">PENDING (Payable)</option>
                        </select>
                    </div>
                    <div x-show="paymentStatus === 'PAID'">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Payment Mode</label>
                        <select name="payment_mode" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="BANK">Bank Account (Current)</option>
                            <option value="CASH">Cash</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showAddExpenseModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
