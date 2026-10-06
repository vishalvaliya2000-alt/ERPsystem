@extends('layouts.app')

@section('title', 'Bulk Item & Price Master Update')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">
    <!-- Header Banner -->
    <div class="bg-white p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-neutral-900 text-[#D7FF53] font-black text-xl flex items-center justify-center shadow-md shrink-0">
                📦
            </div>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#F5F6F8] text-neutral-600 border border-neutral-200/80 font-mono uppercase tracking-wider">Catalog & Pricing</span>
                    <span class="text-xs text-neutral-300">·</span>
                    <span class="text-xs text-neutral-500 font-medium">{{ count($products) }} Master Items</span>
                </div>
                <h1 class="text-2xl font-black text-neutral-900 tracking-tight font-display">Bulk Item & Price Master Update</h1>
                <p class="text-xs text-neutral-500 mt-0.5">Quickly adjust standard rates, costs, HSN codes, and packaging in bulk via CSV spreadsheet or inline grid editor.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a
                href="{{ route('products.bulk-update.export') }}"
                class="flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold text-neutral-900 bg-[#F5F6F8] hover:bg-neutral-100 border border-neutral-200/80 shadow-2xs transition-all cursor-pointer"
            >
                <span>📥</span>
                <span>Download Catalog (CSV)</span>
            </a>
            <a
                href="{{ route('products.index') }}"
                class="flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold text-neutral-600 hover:text-neutral-900 hover:bg-neutral-50 border border-neutral-200/80 transition-all cursor-pointer"
            >
                <span>← Back to Products</span>
            </a>
        </div>
    </div>

    <!-- CSV Upload & Preview Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- CSV Upload Card -->
        <div class="bg-white p-5 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs space-y-4">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">📤</span>
                <div>
                    <h3 class="font-bold text-sm text-neutral-900 font-display">Upload CSV for Bulk Update</h3>
                    <p class="text-[11px] text-neutral-500">Preview changes before committing to DB</p>
                </div>
            </div>

            <form action="{{ route('products.bulk-update.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div class="border-2 border-dashed border-neutral-200 rounded-2xl p-4 text-center hover:border-neutral-400 transition-colors bg-neutral-50/50">
                    <input type="file" name="csv_file" accept=".csv" required class="w-full text-xs text-neutral-600 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[11px] file:font-bold file:bg-neutral-900 file:text-[#D7FF53] cursor-pointer">
                    <p class="text-[10px] text-neutral-400 mt-2 font-mono">Max size: 5MB · CSV format only</p>
                </div>

                <button
                    type="submit"
                    class="w-full py-2.5 rounded-full text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] shadow-xs transition-transform active:scale-95 cursor-pointer"
                >
                    🔍 Inspect & Preview Diff
                </button>
            </form>
        </div>

        <!-- Instructions Card -->
        <div class="md:col-span-2 bg-white p-5 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs space-y-3">
            <h3 class="font-bold text-sm text-neutral-900 font-display flex items-center gap-2">
                <span>📋</span>
                <span>Bulk Update Workflow & Guidelines</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-100 space-y-1">
                    <span class="font-mono font-bold text-neutral-400 block text-[10px]">STEP 1</span>
                    <h4 class="font-bold text-neutral-900">Download CSV</h4>
                    <p class="text-[11px] text-neutral-500">Download the live product list as a structured CSV template.</p>
                </div>
                <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-100 space-y-1">
                    <span class="font-mono font-bold text-neutral-400 block text-[10px]">STEP 2</span>
                    <h4 class="font-bold text-neutral-900">Modify Rates & Costs</h4>
                    <p class="text-[11px] text-neutral-500">Edit standard rates, purchase costs, or HSN in Excel or Google Sheets.</p>
                </div>
                <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-100 space-y-1">
                    <span class="font-mono font-bold text-neutral-400 block text-[10px]">STEP 3</span>
                    <h4 class="font-bold text-neutral-900">Preview & Confirm</h4>
                    <p class="text-[11px] text-neutral-500">Preview all price changes side-by-side and confirm with audit logs.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PREVIEW CONFIRMATION SCREEN (When preview data is available) -->
    @if($previewData)
        <div class="bg-amber-50/60 border border-amber-200 rounded-2xl sm:rounded-3xl p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">⚡</span>
                    <div>
                        <h3 class="font-black text-base text-amber-900 font-display">Pending CSV Update Preview</h3>
                        <p class="text-xs text-amber-700">
                            Found <span class="font-bold">{{ count($previewData['changes']) }} items to update</span>, {{ $previewData['unchanged_count'] }} unchanged items.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <form action="{{ route('products.bulk-update.cancel') }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-full text-xs font-bold text-neutral-600 hover:bg-neutral-100 border border-neutral-200 cursor-pointer">
                            Cancel
                        </button>
                    </form>
                    <form action="{{ route('products.bulk-update.confirm') }}" method="POST">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-full text-xs font-black bg-neutral-900 text-[#D7FF53] hover:bg-black shadow-xs cursor-pointer">
                            ✓ Confirm & Apply {{ count($previewData['changes']) }} Changes
                        </button>
                    </form>
                </div>
            </div>

            @if(!empty($previewData['invalid_rows']))
                <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-800 space-y-1">
                    <span class="font-bold">⚠️ Warnings / Unmatched Rows:</span>
                    <ul class="list-disc list-inside text-[11px] space-y-0.5">
                        @foreach($previewData['invalid_rows'] as $invRow)
                            <li>{{ $invRow }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-x-auto bg-white rounded-2xl border border-amber-200">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-neutral-50 border-b border-neutral-200 text-neutral-400 font-mono uppercase text-[10px]">
                            <th class="p-3 pl-4">SKU</th>
                            <th class="p-3">Product Name</th>
                            <th class="p-3">Changes Detected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach($previewData['changes'] as $c)
                            <tr class="hover:bg-neutral-50/50">
                                <td class="p-3 pl-4 font-mono font-bold text-neutral-900">{{ $c['sku'] }}</td>
                                <td class="p-3 font-medium text-neutral-700">{{ $c['name'] }}</td>
                                <td class="p-3 space-x-2">
                                    @foreach($c['diffs'] as $field => $d)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-mono bg-amber-50 text-amber-900 border border-amber-200">
                                            <span class="font-bold">{{ str_replace('_', ' ', $field) }}:</span>
                                            <span class="line-through text-neutral-400">{{ $d['old'] }}</span>
                                            <span>→</span>
                                            <span class="font-bold text-emerald-700">{{ $d['new'] }}</span>
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Inline Grid Batch Editor -->
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
        <form action="{{ route('products.bulk-update.grid') }}" method="POST">
            @csrf
            <div class="p-4 sm:p-5 border-b border-neutral-100 flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h3 class="font-bold text-base text-neutral-900 font-display">Inline Price & Cost Grid Editor</h3>
                    <p class="text-xs text-neutral-500">Edit values directly in table and click save to apply across all products.</p>
                </div>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-full text-xs font-black bg-neutral-900 text-[#D7FF53] hover:bg-black shadow-xs cursor-pointer transition-transform active:scale-95"
                >
                    💾 Save All Grid Changes
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-neutral-400 font-mono uppercase text-[10px]">
                            <th class="p-3 pl-6">SKU</th>
                            <th class="p-3">Product Name</th>
                            <th class="p-3">Standard Rate (₹)</th>
                            <th class="p-3">Cost Price (₹)</th>
                            <th class="p-3">HSN Code</th>
                            <th class="p-3">Min Qty</th>
                            <th class="p-3 pr-6">Packaging Spec</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach($products as $idx => $p)
                            <tr class="hover:bg-neutral-50/50 transition-colors">
                                <td class="p-3 pl-6 font-mono font-bold text-neutral-900">
                                    <input type="hidden" name="products[{{ $idx }}][id]" value="{{ $p->id }}">
                                    {{ $p->product_code }}
                                </td>
                                <td class="p-3 font-medium text-neutral-800">
                                    {{ $p->product_name }}
                                </td>
                                <td class="p-3">
                                    <input
                                        type="number"
                                        step="0.01"
                                        name="products[{{ $idx }}][standard_rate]"
                                        value="{{ $p->standard_rate }}"
                                        class="w-24 px-2 py-1 border border-neutral-200 rounded-lg text-xs font-mono font-bold text-neutral-900 focus:border-neutral-800"
                                    >
                                </td>
                                <td class="p-3">
                                    <input
                                        type="number"
                                        step="0.01"
                                        name="products[{{ $idx }}][standard_cost]"
                                        value="{{ $p->standard_cost }}"
                                        class="w-24 px-2 py-1 border border-neutral-200 rounded-lg text-xs font-mono text-neutral-700 focus:border-neutral-800"
                                    >
                                </td>
                                <td class="p-3">
                                    <input
                                        type="text"
                                        name="products[{{ $idx }}][hsn_code]"
                                        value="{{ $p->hsn_code }}"
                                        class="w-28 px-2 py-1 border border-neutral-200 rounded-lg text-xs font-mono text-neutral-700 focus:border-neutral-800"
                                    >
                                </td>
                                <td class="p-3">
                                    <input
                                        type="number"
                                        step="1"
                                        name="products[{{ $idx }}][min_order_qty]"
                                        value="{{ $p->min_order_qty }}"
                                        class="w-20 px-2 py-1 border border-neutral-200 rounded-lg text-xs font-mono text-neutral-700 focus:border-neutral-800"
                                    >
                                </td>
                                <td class="p-3 pr-6">
                                    <input
                                        type="text"
                                        name="products[{{ $idx }}][packaging]"
                                        value="{{ $p->packaging }}"
                                        class="w-48 px-2 py-1 border border-neutral-200 rounded-lg text-xs text-neutral-700 focus:border-neutral-800"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
@endsection
