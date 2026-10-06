@extends('layouts.app')

@section('title', 'Chart of Accounts')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddAccountModal: false,
    selectedGroup: '{{ $groupFilter ?? 'ALL' }}'
}">
    <!-- Accounting Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('accounting.chart') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                📚 Chart of Accounts
            </a>
            <a href="{{ route('accounting.vouchers') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
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
        <button @click="showAddAccountModal = true" class="px-4 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
            <span>+</span> Add Account
        </button>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Assets</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format($totals['assets'] ?? 0, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($groupedAccounts['ASSETS'] ?? []) }} accounts</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Liabilities</span>
            <div class="text-lg font-black text-rose-600 mt-1">₹{{ number_format($totals['liabilities'] ?? 0, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($groupedAccounts['LIABILITIES'] ?? []) }} accounts</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Equity & Capital</span>
            <div class="text-lg font-black text-purple-600 mt-1">₹{{ number_format($totals['equity'] ?? 0, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($groupedAccounts['EQUITY'] ?? []) }} accounts</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Sales & Income</span>
            <div class="text-lg font-black text-blue-600 mt-1">₹{{ number_format($totals['income'] ?? 0, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($groupedAccounts['INCOME'] ?? []) }} accounts</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Expenses</span>
            <div class="text-lg font-black text-amber-600 mt-1">₹{{ number_format($totals['expenses'] ?? 0, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($groupedAccounts['EXPENSES'] ?? []) }} accounts</div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white border border-neutral-200 p-3 rounded-2xl shadow-xs">
        <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto">
            @php $groups = ['ALL' => 'All Groups', 'ASSETS' => 'Assets', 'LIABILITIES' => 'Liabilities', 'EQUITY' => 'Equity', 'INCOME' => 'Income', 'EXPENSES' => 'Expenses']; @endphp
            @foreach($groups as $k => $label)
                <a href="{{ route('accounting.chart', array_merge(request()->query(), ['group' => $k === 'ALL' ? null : $k])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors whitespace-nowrap {{ ($groupFilter == $k || (!$groupFilter && $k === 'ALL')) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('accounting.chart') }}" class="w-full sm:w-72">
            @if($groupFilter) <input type="hidden" name="group" value="{{ $groupFilter }}"> @endif
            <div class="relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search code, name, or group..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 transition-colors">
                <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </form>
    </div>

    <!-- Accounts Groups Accordion / Bento Grid -->
    <div class="space-y-6">
        @foreach(['ASSETS' => ['Assets', 'border-emerald-200 bg-emerald-50/20 text-emerald-800'], 'LIABILITIES' => ['Liabilities', 'border-rose-200 bg-rose-50/20 text-rose-800'], 'EQUITY' => ['Equity', 'border-purple-200 bg-purple-50/20 text-purple-800'], 'INCOME' => ['Income', 'border-blue-200 bg-blue-50/20 text-blue-800'], 'EXPENSES' => ['Expenses', 'border-amber-200 bg-amber-50/20 text-amber-800']] as $grpKey => $grpMeta)
            @if(!$groupFilter || $groupFilter === $grpKey)
                @php $accList = $groupedAccounts[$grpKey] ?? collect(); @endphp
                <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3 border-b border-neutral-100 flex items-center justify-between {{ $grpMeta[1] }}">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm uppercase tracking-wider">{{ $grpMeta[0] }}</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-white/70">{{ count($accList) }}</span>
                        </div>
                        <span class="font-mono font-bold text-xs">Total: ₹{{ number_format($totals[strtolower($grpKey)] ?? 0, 2) }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-neutral-50/70 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-4 font-bold">Code</th>
                                    <th class="py-2.5 px-4 font-bold">Account Name</th>
                                    <th class="py-2.5 px-4 font-bold">Sub-Group</th>
                                    <th class="py-2.5 px-4 font-bold text-right">Opening Bal</th>
                                    <th class="py-2.5 px-4 font-bold text-right">Current Balance</th>
                                    <th class="py-2.5 px-4 font-bold text-center">Type</th>
                                    <th class="py-2.5 px-4 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse($accList as $acc)
                                    <tr class="hover:bg-neutral-50/60 transition-colors">
                                        <td class="py-3 px-4 font-mono font-bold text-neutral-800">{{ $acc->account_code }}</td>
                                        <td class="py-3 px-4 font-medium text-neutral-900">
                                            <a href="{{ route('accounting.ledger', $acc->id) }}" class="hover:underline flex items-center gap-1.5">
                                                <span>{{ $acc->account_name }}</span>
                                                <svg class="w-3 h-3 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        </td>
                                        <td class="py-3 px-4 text-neutral-500 text-[11px]">
                                            <span class="px-2 py-0.5 rounded-md bg-neutral-100 font-mono">{{ $acc->sub_group }}</span>
                                        </td>
                                        <td class="py-3 px-4 font-mono text-neutral-500 text-right">₹{{ number_format((float)$acc->opening_balance, 2) }}</td>
                                        <td class="py-3 px-4 font-mono font-bold text-right {{ (float)$acc->current_balance >= 0 ? 'text-neutral-900' : 'text-rose-600' }}">
                                            ₹{{ number_format((float)$acc->current_balance, 2) }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            @if($acc->is_system)
                                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-md bg-neutral-100 text-neutral-600">SYSTEM</span>
                                            @else
                                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-md bg-blue-50 text-blue-600">CUSTOM</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <a href="{{ route('accounting.ledger', $acc->id) }}" class="inline-flex items-center px-2.5 py-1 rounded-lg border border-neutral-200 text-[11px] font-semibold text-neutral-700 hover:bg-neutral-100 transition-colors">
                                                Statement
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-neutral-400">No accounts found in this group.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <!-- Modal: Add Custom Account -->
    <div x-show="showAddAccountModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAddAccountModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-sm">Add New Ledger Account</h3>
                <button @click="showAddAccountModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('accounting.accounts.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Account Code *</label>
                    <input type="text" name="account_code" required placeholder="e.g. 5050" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Account Name *</label>
                    <input type="text" name="account_name" required placeholder="e.g. Marketing & Advertising" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Primary Group *</label>
                    <select name="account_group" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                        <option value="ASSETS">ASSETS</option>
                        <option value="LIABILITIES">LIABILITIES</option>
                        <option value="EQUITY">EQUITY</option>
                        <option value="INCOME">INCOME</option>
                        <option value="EXPENSES" selected>EXPENSES</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Sub-Group / Classification *</label>
                    <input type="text" name="sub_group" required placeholder="e.g. INDIRECT_EXPENSES" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono uppercase">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Opening Balance (₹)</label>
                    <input type="number" step="0.01" name="opening_balance" value="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showAddAccountModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
