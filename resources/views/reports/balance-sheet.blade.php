@extends('layouts.app')

@section('title', 'Statutory Balance Sheet')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Reports Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('reports.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📊 Reports Hub
            </a>
            <a href="{{ route('reports.balance-sheet') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
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

        <div class="flex items-center gap-2">
            <a href="{{ route('reports.balance-sheet.pdf', ['as_of_date' => $asOfDate, 'firm_id' => $firmId]) }}"
               class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📄</span> Export PDF
            </a>
            <a href="{{ route('reports.balance-sheet.excel', ['as_of_date' => $asOfDate, 'firm_id' => $firmId]) }}"
               class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📊</span> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('reports.balance-sheet') }}" class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">As of Date</label>
                <input type="date" name="as_of_date" value="{{ \Carbon\Carbon::parse($asOfDate)->format('Y-m-d') }}"
                       class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
            </div>

            @if($firms->isNotEmpty())
            <div>
                <label class="block text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1">Firm</label>
                <select name="firm_id" class="px-3 py-1.5 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden">
                    <option value="">All Operating Firms</option>
                    @foreach($firms as $f)
                        <option value="{{ $f->id }}" {{ $firmId == $f->id ? 'selected' : '' }}>{{ $f->firm_name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="self-end">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Update Statement
                </button>
            </div>
        </div>

        <!-- Balance Status Badge -->
        <div class="flex items-center gap-2">
            @if($data['is_balanced'])
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Statement Balanced: ₹{{ number_format($data['assets']['grand_total'], 2) }}</span>
                </div>
            @else
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>Imbalance: ₹{{ number_format(abs($data['difference']), 2) }}</span>
                </div>
            @endif
        </div>
    </form>

    <!-- Two-Column Statement Layout (Bento Style) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <!-- 1. ASSETS COLUMN -->
        <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-5 border-b border-neutral-100 bg-neutral-50/50 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-neutral-800">Assets</h2>
                    <p class="text-[11px] text-neutral-500">Application of economic resources</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-neutral-400">Total Assets</span>
                    <div class="text-lg font-black text-emerald-600">₹{{ number_format($data['assets']['grand_total'], 2) }}</div>
                </div>
            </div>

            <div class="p-5 space-y-6">
                <!-- Current Assets Section -->
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-black uppercase text-neutral-700 tracking-wide">1. Current Assets</span>
                        <span class="text-xs font-bold text-neutral-900">₹{{ number_format($data['assets']['current']['total'], 2) }}</span>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-neutral-600 font-medium">Bank & Cash Balances</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['current']['bank_and_cash_total'], 2) }}</span>
                        </div>
                        @foreach($data['assets']['current']['bank_and_cash'] as $acc)
                            <div class="flex justify-between items-center pl-4 py-0.5 text-[11px] text-neutral-500">
                                <a href="{{ route('accounting.ledger', $acc->id) }}" class="hover:text-neutral-900 hover:underline">
                                    {{ $acc->account_code }} - {{ $acc->account_name }}
                                </a>
                                <span class="font-mono">₹{{ number_format($acc->current_balance, 2) }}</span>
                            </div>
                        @endforeach

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Sundry Debtors (Trade Receivables)</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['current']['sundry_debtors_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">GST Input Tax Credit (ITC Receivable)</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['current']['gst_input_itc_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Inventory & Stock on Hand</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['current']['inventory_total'], 2) }}</span>
                        </div>

                        @if($data['assets']['current']['other_current_total'] > 0)
                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Other Current Assets / Advances</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['current']['other_current_total'], 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Fixed Assets Section -->
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-black uppercase text-neutral-700 tracking-wide">2. Fixed & Non-Current Assets</span>
                        <span class="text-xs font-bold text-neutral-900">₹{{ number_format($data['assets']['fixed']['net_total'], 2) }}</span>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-neutral-600 font-medium">Gross Tangible Fixed Assets</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['fixed']['tangible_total'], 2) }}</span>
                        </div>
                        @foreach($data['assets']['fixed']['tangible'] as $acc)
                            <div class="flex justify-between items-center pl-4 py-0.5 text-[11px] text-neutral-500">
                                <a href="{{ route('accounting.ledger', $acc->id) }}" class="hover:text-neutral-900 hover:underline">
                                    {{ $acc->account_code }} - {{ $acc->account_name }}
                                </a>
                                <span class="font-mono">₹{{ number_format($acc->current_balance, 2) }}</span>
                            </div>
                        @endforeach

                        <div class="flex justify-between items-center py-1 text-rose-600 border-t border-dashed border-neutral-100">
                            <span class="font-medium">Less: Accumulated Depreciation</span>
                            <span class="font-mono font-bold">-₹{{ number_format($data['assets']['fixed']['accumulated_depreciation_total'], 2) }}</span>
                        </div>

                        @if($data['assets']['fixed']['other_total'] > 0)
                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Other Non-Current Assets</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['assets']['fixed']['other_total'], 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="p-4 bg-neutral-900 text-white flex items-center justify-between rounded-b-2xl">
                <span class="text-xs font-bold uppercase tracking-wider">Total Assets</span>
                <span class="text-base font-black font-mono text-[#D7FF53]">₹{{ number_format($data['assets']['grand_total'], 2) }}</span>
            </div>
        </div>

        <!-- 2. LIABILITIES & EQUITY COLUMN -->
        <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-5 border-b border-neutral-100 bg-neutral-50/50 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-neutral-800">Liabilities & Equity</h2>
                    <p class="text-[11px] text-neutral-500">Sources of business capital and obligations</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-neutral-400">Total Obligations</span>
                    <div class="text-lg font-black text-rose-600">₹{{ number_format($data['total_liabilities_and_equity'], 2) }}</div>
                </div>
            </div>

            <div class="p-5 space-y-6">
                <!-- Current Liabilities Section -->
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-black uppercase text-neutral-700 tracking-wide">1. Current Liabilities</span>
                        <span class="text-xs font-bold text-neutral-900">₹{{ number_format($data['liabilities']['current']['total'], 2) }}</span>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-neutral-600 font-medium">Sundry Creditors (Trade Payables)</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['liabilities']['current']['sundry_creditors_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Output GST Duties & Taxes Payable</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['liabilities']['current']['output_gst_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">TCS / TDS Withholding Payable</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['liabilities']['current']['tcs_tds_total'], 2) }}</span>
                        </div>

                        @if($data['liabilities']['current']['other_current_total'] > 0)
                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Other Current Liabilities</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['liabilities']['current']['other_current_total'], 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Non-Current Liabilities Section -->
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-black uppercase text-neutral-700 tracking-wide">2. Non-Current Liabilities</span>
                        <span class="text-xs font-bold text-neutral-900">₹{{ number_format($data['liabilities']['non_current']['total'], 2) }}</span>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-neutral-600 font-medium">Long Term Debt & Term Loans</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['liabilities']['non_current']['total'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Shareholders' Equity & Reserves -->
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-black uppercase text-neutral-700 tracking-wide">3. Equity & Reserves</span>
                        <span class="text-xs font-bold text-purple-700">₹{{ number_format($data['equity']['total'], 2) }}</span>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-neutral-600 font-medium">Owner Capital / Share Capital</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['equity']['share_capital_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100">
                            <span class="text-neutral-600 font-medium">Retained Reserves & Surplus</span>
                            <span class="font-mono font-bold text-neutral-800">₹{{ number_format($data['equity']['reserves_total'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-1 border-t border-dashed border-neutral-100 {{ $data['equity']['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            <span class="font-bold">Current Period Net Profit / (Loss)</span>
                            <span class="font-mono font-bold">₹{{ number_format($data['equity']['net_profit'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-neutral-900 text-white flex items-center justify-between rounded-b-2xl">
                <span class="text-xs font-bold uppercase tracking-wider">Total Liabilities & Equity</span>
                <span class="text-base font-black font-mono text-[#D7FF53]">₹{{ number_format($data['total_liabilities_and_equity'], 2) }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
