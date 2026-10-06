@extends('layouts.app')

@section('title', 'Fixed Assets Register')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddAssetModal: false,
    showBatchDepModal: false,
    cost: '',
    salvage: '0',
    usefulLife: '10',
    depMethod: 'SLM',
    depRate: '10',
    updateRate() {
        let ul = parseInt(this.usefulLife) || 1;
        if (this.depMethod === 'SLM') {
            this.depRate = (100 / ul).toFixed(2);
        } else {
            this.depRate = ((100 / ul) * 1.5).toFixed(2);
        }
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
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                🧾 Expenses & ITC
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                🏭 Fixed Assets
            </a>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showBatchDepModal = true" class="px-4 py-2.5 rounded-xl border border-neutral-200 hover:bg-neutral-100 text-neutral-800 text-xs font-bold transition-colors shadow-xs">
                ⚡ Batch Depreciation
            </button>
            <button @click="showAddAssetModal = true" class="px-4 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                <span>+</span> Register Asset
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Original Cost</span>
            <div class="text-lg font-black text-neutral-900 mt-1">₹{{ number_format($totalOriginalCost, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Gross asset value</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Accumulated Depreciation</span>
            <div class="text-lg font-black text-rose-600 mt-1">₹{{ number_format($totalDepreciation, 2) }}</div>
            <div class="text-[10px] text-rose-500 font-semibold mt-0.5">Written-off to date</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Net Book Value</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format($totalNetBookValue, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Current balance sheet value</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Active Capital Assets</span>
            <div class="text-lg font-black text-neutral-900 mt-1">{{ $activeAssetCount }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Operational units</div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white border border-neutral-200 p-4 rounded-2xl shadow-xs">
        <form method="GET" action="{{ route('fixed-assets.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Asset Category</label>
                <select name="category" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="">All Categories</option>
                    @foreach(['PLANT_MACHINERY', 'VEHICLES', 'COMPUTERS', 'FURNITURE', 'OFFICE_EQUIPMENT'] as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ str_replace('_', ' ', $cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-1.5 rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                    <option value="DISPOSED" {{ $status === 'DISPOSED' ? 'selected' : '' }}>DISPOSED</option>
                    <option value="ALL" {{ $status === 'ALL' ? 'selected' : '' }}>ALL</option>
                </select>
            </div>
            <div class="flex items-end gap-2 col-span-2">
                <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl font-bold transition-colors w-full sm:w-auto">Filter</button>
                <a href="{{ route('fixed-assets.index') }}" class="px-3 py-2 border border-neutral-200 text-neutral-600 hover:bg-neutral-100 rounded-xl font-bold transition-colors">Reset</a>
            </div>
        </form>
    </div>

    <!-- Assets Table -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Code</th>
                        <th class="py-3 px-4 font-bold">Asset Name</th>
                        <th class="py-3 px-4 font-bold">Category</th>
                        <th class="py-3 px-4 font-bold">Purchase Date</th>
                        <th class="py-3 px-4 font-bold text-right">Cost (₹)</th>
                        <th class="py-3 px-4 font-bold text-center">Method</th>
                        <th class="py-3 px-4 font-bold text-right">Accumulated Depr (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Current Book Value (₹)</th>
                        <th class="py-3 px-4 font-bold text-center">Status</th>
                        <th class="py-3 px-4 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($assets as $a)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-neutral-800">{{ $a->asset_code }}</td>
                            <td class="py-3 px-4 font-medium text-neutral-900">
                                <a href="{{ route('fixed-assets.show', $a->id) }}" class="hover:underline flex items-center gap-1.5 font-bold">
                                    <span>{{ $a->asset_name }}</span>
                                    <svg class="w-3 h-3 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </td>
                            <td class="py-3 px-4 text-neutral-500 text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-neutral-100 font-mono">{{ str_replace('_', ' ', $a->asset_category) }}</span>
                            </td>
                            <td class="py-3 px-4 text-neutral-600 whitespace-nowrap">{{ $a->purchase_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-right text-neutral-900">₹{{ number_format((float)$a->purchase_cost, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-indigo-50 text-indigo-700">
                                    {{ $a->depreciation_method }} ({{ (float)$a->depreciation_rate }}%)
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-right text-rose-600">₹{{ number_format((float)$a->accumulated_depreciation, 2) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-right text-emerald-700">₹{{ number_format((float)$a->current_book_value, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($a->status === 'ACTIVE')
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-700">ACTIVE</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-600">{{ $a->status }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('fixed-assets.show', $a->id) }}" class="inline-flex items-center px-2.5 py-1 rounded-lg border border-neutral-200 text-[11px] font-semibold text-neutral-700 hover:bg-neutral-100 transition-colors">
                                    Schedule & Run
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-neutral-400">No fixed assets registered in this unit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assets->hasPages())
            <div class="px-5 py-3 border-t border-neutral-100">
                {{ $assets->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Register Fixed Asset -->
    <div x-show="showAddAssetModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAddAssetModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Register New Capital Fixed Asset</h3>
                <button @click="showAddAssetModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('fixed-assets.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Asset Code *</label>
                        <input type="text" name="asset_code" required placeholder="e.g. FA-001" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Category *</label>
                        <select name="asset_category" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="PLANT_MACHINERY">Plant & Machinery</option>
                            <option value="VEHICLES">Vehicles</option>
                            <option value="COMPUTERS">Computers & IT Hardware</option>
                            <option value="FURNITURE">Furniture & Fixtures</option>
                            <option value="OFFICE_EQUIPMENT">Office Equipment</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Asset Description / Name *</label>
                    <input type="text" name="asset_name" required placeholder="e.g. Continuous Onion Dehydration Line #2" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Purchase Date *</label>
                        <input type="date" name="purchase_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Purchase Cost (₹) *</label>
                        <input type="number" step="0.01" name="purchase_cost" required x-model="cost" placeholder="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Salvage Value (₹)</label>
                        <input type="number" step="0.01" name="salvage_value" x-model="salvage" placeholder="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Useful Life (Years) *</label>
                        <input type="number" name="useful_life_years" required x-model="usefulLife" @input="updateRate()" min="1" max="50" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Method *</label>
                        <select name="depreciation_method" x-model="depMethod" @change="updateRate()" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="SLM">Straight Line (SLM)</option>
                            <option value="WDV">Written Down (WDV)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Annual Rate (%) *</label>
                        <input type="number" step="0.01" name="depreciation_rate" x-model="depRate" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showAddAssetModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Save Asset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Batch Depreciation Runner -->
    <div x-show="showBatchDepModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showBatchDepModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Run Batch Asset Depreciation</h3>
                <button @click="showBatchDepModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <p class="text-xs text-neutral-600">
                This action computes and posts double-entry depreciation journal vouchers for all {{ $activeAssetCount }} active assets in the current firm that have remaining book value above salvage.
            </p>

            <form method="POST" action="{{ route('fixed-assets.batch-depreciation') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Depreciation Period *</label>
                    <select name="period" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                        <option value="YEAR">Annual Depreciation (Full Year)</option>
                        <option value="MONTH">Monthly Depreciation (1/12th Year)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showBatchDepModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Execute Batch Run</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
