@extends('layouts.app')

@section('title', 'Asset Details - ' . $asset->asset_name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showDepModal: false,
    showDisposeModal: false
}">
    <!-- Sub-Nav & Back button -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('fixed-assets.index') }}" class="p-2 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-neutral-500">[{{ $asset->asset_code }}]</span>
                    <h2 class="text-base font-black text-neutral-900">{{ $asset->asset_name }}</h2>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-neutral-100 text-neutral-700 font-mono">{{ str_replace('_', ' ', $asset->asset_category) }}</span>
                    @if($asset->status === 'ACTIVE')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-700">ACTIVE</span>
                    @else
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-50 text-rose-700">{{ $asset->status }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($asset->status === 'ACTIVE' && (float)$asset->current_book_value > (float)$asset->salvage_value)
                <button @click="showDepModal = true" class="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-xs">
                    ⚡ Charge Depreciation
                </button>
                <button @click="showDisposeModal = true" class="px-3 py-2 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-bold transition-colors">
                    Dispose / Sell Asset
                </button>
            @endif
        </div>
    </div>

    <!-- Asset Specification Card Bento -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Original Cost</span>
            <div class="text-lg font-black text-neutral-900 mt-1">₹{{ number_format((float)$asset->purchase_cost, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Purchased {{ $asset->purchase_date->format('d M Y') }}</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Current Net Book Value</span>
            <div class="text-lg font-black text-emerald-600 mt-1">₹{{ number_format((float)$asset->current_book_value, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Salvage floor: ₹{{ number_format((float)$asset->salvage_value, 2) }}</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Total Depreciated</span>
            <div class="text-lg font-black text-rose-600 mt-1">₹{{ number_format((float)$asset->accumulated_depreciation, 2) }}</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">{{ count($depreciations) }} entries recorded</div>
        </div>
        <div class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Depreciation Model</span>
            <div class="text-base font-black text-indigo-700 mt-1">{{ $asset->depreciation_method }} ({{ (float)$asset->depreciation_rate }}%)</div>
            <div class="text-[10px] text-neutral-400 mt-0.5">Useful Life: {{ $asset->useful_life_years }} years</div>
        </div>
    </div>

    <!-- Suggested Projections Banner -->
    @if($asset->status === 'ACTIVE')
        <div class="p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div>
                <span class="font-bold text-indigo-950">Computed Depreciation Projections:</span>
                <span class="ml-2 text-indigo-800">Annual: <strong>₹{{ number_format($calculatedAnnual, 2) }}</strong></span>
                <span class="mx-2 text-indigo-300">|</span>
                <span class="text-indigo-800">Monthly: <strong>₹{{ number_format($calculatedMonthly, 2) }}</strong></span>
            </div>
            <span class="text-indigo-600 font-mono text-[11px]">Ready for one-click journal creation</span>
        </div>
    @endif

    <!-- Depreciation History & Schedule -->
    <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between">
            <h3 class="font-black text-neutral-900 text-base">Depreciation Schedule & Journal History</h3>
            <span class="text-xs text-neutral-500 font-mono">{{ count($depreciations) }} Entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 font-bold">Depreciation Date</th>
                        <th class="py-3 px-4 font-bold text-right">Depreciation Amount (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Book Value Before (₹)</th>
                        <th class="py-3 px-4 font-bold text-right">Book Value After (₹)</th>
                        <th class="py-3 px-4 font-bold">Linked Journal Voucher</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($depreciations as $dep)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="py-3 px-4 text-neutral-700 whitespace-nowrap">{{ $dep->depreciation_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-rose-600 text-right">₹{{ number_format((float)$dep->depreciation_amount, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-neutral-600 text-right">₹{{ number_format((float)$dep->book_value_before, 2) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-emerald-700 text-right">₹{{ number_format((float)$dep->book_value_after, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-neutral-800">
                                @if($dep->journalVoucher)
                                    <span class="font-bold text-neutral-900">{{ $dep->journalVoucher->voucher_number }}</span>
                                    <span class="text-neutral-400 text-[10px] ml-1">({{ $dep->journalVoucher->voucher_type }})</span>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-neutral-400">No depreciation entries charged yet for this asset.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Charge Single Asset Depreciation -->
    <div x-show="showDepModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showDepModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Charge Depreciation</h3>
                <button @click="showDepModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('fixed-assets.depreciate', $asset->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Depreciation Date *</label>
                    <input type="date" name="depreciation_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Depreciation Amount (₹) *</label>
                    <input type="number" step="0.01" name="depreciation_amount" value="{{ $calculatedAnnual }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    <p class="text-[11px] text-neutral-500 mt-1">Suggested Annual: ₹{{ number_format($calculatedAnnual, 2) }} | Monthly: ₹{{ number_format($calculatedMonthly, 2) }}</p>
                </div>

                <div class="p-3 rounded-xl bg-neutral-50 border border-neutral-200 text-xs text-neutral-600">
                    ℹ️ Charging depreciation creates a balanced double-entry voucher:
                    <div class="mt-1 font-mono text-[11px]">
                        Dr. Depreciation Expense (5030)<br>
                        Cr. Fixed Assets (1050)
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showDepModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Post Depreciation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Dispose Asset -->
    <div x-show="showDisposeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showDisposeModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-rose-600 text-base">Dispose / Sell Fixed Asset</h3>
                <button @click="showDisposeModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('fixed-assets.dispose', $asset->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Disposal Date *</label>
                    <input type="date" name="disposal_date" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Sale Proceeds / Scrap Value Received (₹) *</label>
                    <input type="number" step="0.01" name="sale_proceeds" required value="0.00" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    <p class="text-[11px] text-neutral-500 mt-1">Current Book Value: ₹{{ number_format((float)$asset->current_book_value, 2) }}</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Reason / Notes</label>
                    <input type="text" name="reason" placeholder="e.g. Obsolete machinery replaced" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showDisposeModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-xs transition-colors">Confirm Disposal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
