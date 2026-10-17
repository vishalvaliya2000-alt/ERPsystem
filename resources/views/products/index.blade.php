@extends('layouts.app')

@section('title', 'Product Catalog & HSN Rates')

@section('content')
<div class="space-y-6" x-data="productsManager()">
    <!-- Header -->
    <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs">
        <div>
            <h2 class="text-xl font-black text-neutral-900 flex items-center gap-2">
                <span>📦</span> Products Catalog
            </h2>
            <p class="text-xs text-neutral-500 mt-1">
                Standard Dehydration Export SKUs, packaging specs, live GST HSN master, and technical documentation dossier (Photos, COA, Spec Sheets, MSDS).
            </p>
        </div>

        <!-- Action & Filter Controls -->
        <div class="flex items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2 w-full sm:w-auto">
                <input type="text" name="q" placeholder="Search SKU, name, or HSN..." value="{{ $search }}"
                    class="px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] w-full sm:w-56 font-medium">
            </form>

            <a href="{{ route('products.bulk-update.index') }}"
                class="px-4 py-2.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 font-extrabold text-xs rounded-full transition-all flex items-center gap-2 border border-neutral-300 shrink-0">
                <span>⚡</span>
                <span>Bulk Update</span>
            </a>

            <button @click="openAddModal()"
                class="px-4 py-2.5 bg-[#091315] hover:bg-black text-[#D7FF53] font-extrabold text-xs rounded-full shadow-2xs transition-all flex items-center gap-2 cursor-pointer border border-neutral-800 shrink-0">
                <span>➕</span>
                <span>Add Product</span>
            </button>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700">Catalog SKUs ({{ $products->count() }})</span>
            <span class="text-[11px] text-slate-500">Click document badges to preview, download, or share technical specs & certificates</span>
        </div>

        @if($products->isEmpty())
        <div class="p-12 text-center space-y-3">
            <div
                class="w-14 h-14 rounded-3xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mx-auto font-bold shadow-xs">
                📦
            </div>
            <h3 class="text-sm font-bold text-slate-800">No Products in Catalog</h3>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">Add your standard Dehydrated Onion, Garlic, and Vegetable
                products with SKU codes, packaging specifications, and reference market rates.</p>
            <button @click="openAddModal()"
                class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs cursor-pointer">
                + Add First Product
            </button>
        </div>
        @else
        <div class="overflow-x-auto touch-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 font-bold border-b border-slate-200">
                        <th class="p-3.5">SKU Code</th>
                        <th class="p-3.5">Product Description & Cut</th>
                        <th class="p-3.5">Category</th>
                        <th class="p-3.5">HSN Code</th>
                        <th class="p-3.5 text-right">Market Reference Rate / KG</th>
                        <th class="p-3.5 text-center">GST</th>
                        <th class="p-3.5">Documents & Media</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($products as $prod)
                    @php
                    $nameLower = strtolower($prod->product_name);
                    if (str_contains($nameLower, 'garlic')) {
                        $catLabel = 'Garlic';
                        $catClass = 'bg-amber-100 text-amber-900 border-amber-200';
                    } elseif (str_contains($nameLower, 'onion')) {
                        $catLabel = 'Onion';
                        $catClass = 'bg-rose-100 text-rose-900 border-rose-200';
                    } elseif (str_contains($nameLower, 'cabbage') || str_contains($nameLower, 'carrot') ||
                        str_contains($nameLower, 'beet') || str_contains($nameLower, 'spinach') || str_contains($nameLower,
                        'tomato') || str_contains($nameLower, 'potato') || str_contains($nameLower, 'vegetable')) {
                        $catLabel = 'Vegetables';
                        $catClass = 'bg-emerald-100 text-emerald-900 border-emerald-200';
                    } elseif (str_contains($nameLower, 'ginger') || str_contains($nameLower, 'turmeric') ||
                        str_contains($nameLower, 'coriander') || str_contains($nameLower, 'cumin') ||
                        str_contains($nameLower, 'methi') || str_contains($nameLower, 'chilli') || str_contains($nameLower,
                        'spice')) {
                        $catLabel = 'Spices';
                        $catClass = 'bg-purple-100 text-purple-900 border-purple-200';
                    } elseif (str_contains($nameLower, 'box') || str_contains($nameLower, 'bag') ||
                        str_contains($nameLower, 'liner') || str_contains($nameLower, 'raffia')) {
                        $catLabel = 'Packaging';
                        $catClass = 'bg-slate-100 text-slate-800 border-slate-200';
                    } else {
                        $catLabel = 'Agro';
                        $catClass = 'bg-blue-100 text-blue-900 border-blue-200';
                    }

                    $badgeSummary = $prod->document_badge_summary;
                    $totalAssets = $prod->documents->count();
                    @endphp
                    <tr class="hover:bg-amber-50/40 transition-colors">
                        <td class="p-3.5 font-mono font-black text-slate-900 text-sm tracking-wide">
                            <a href="{{ route('products.show', $prod->id) }}" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-[#D7FF53] hover:text-[#091315] border border-slate-200 transition-colors inline-block" title="View Product Dossier">
                                {{ $prod->product_code }}
                            </a>
                        </td>
                        <td class="p-3.5">
                            <a href="{{ route('products.show', $prod->id) }}" class="font-bold text-slate-900 text-sm block hover:underline">
                                {{ $prod->product_name }}
                            </a>
                            <span class="text-[11px] text-slate-500">{{ $prod->packaging ?: '20 KGs Two Poly Liner Bags with Laminated Paper Bag' }}</span>
                        </td>
                        <td class="p-3.5">
                            <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase border {{ $catClass }}">
                                {{ $catLabel }}
                            </span>
                        </td>
                        <td class="p-3.5 font-mono font-bold text-slate-700">
                            <span class="px-2 py-0.5 rounded bg-slate-50 border border-slate-200">{{ $prod->hsn_code ?: '07129090' }}</span>
                        </td>
                        <td class="p-3.5 text-right font-black text-slate-900 text-sm">
                            ₹{{ number_format($prod->standard_rate ?: $prod->current_rate_per_kg, 2) }}
                            <span class="text-[10px] text-slate-400 font-normal block">Base Rate</span>
                        </td>
                        <td class="p-3.5 text-center">
                            @php
                                $displayTax = (float) ($prod->tax_rate ?? $prod->tax_rate_percent ?? 5);
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-md font-bold text-[11px] {{ $displayTax == 0 ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ rtrim(rtrim(number_format($displayTax, 2), '0'), '.') }}%
                            </span>
                        </td>

                        <!-- Product Documents & Media Indicators -->
                        <td class="p-3.5">
                            <div class="flex items-center gap-1.5 flex-wrap cursor-pointer group"
                                @click="openQuickDocsModal(@js($prod))"
                                title="Click to view documents, preview, download, or share">
                                @if($totalAssets === 0)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-neutral-100 group-hover:bg-[#D7FF53] group-hover:text-[#091315] text-neutral-500 font-bold text-[10px] transition-colors border border-dashed border-neutral-300">
                                        <span>➕</span>
                                        <span>Add Docs</span>
                                    </span>
                                @else
                                    @if($badgeSummary['photos_count'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-[10px] border border-indigo-200/70"
                                            title="{{ $badgeSummary['photos_count'] }} Photo(s)">
                                            <span>📸</span>
                                            <span class="font-mono">{{ $badgeSummary['photos_count'] }}</span>
                                        </span>
                                    @endif

                                    @if($badgeSummary['has_coa'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[10px] border border-emerald-200/70"
                                            title="Certificate of Analysis (COA) Available">
                                            <span>📜</span>
                                            <span>COA</span>
                                        </span>
                                    @endif

                                    @if($badgeSummary['has_specification'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-200/70"
                                            title="Technical Data / Specification Sheet Available">
                                            <span>📋</span>
                                            <span>Specs</span>
                                        </span>
                                    @endif

                                    @if($badgeSummary['has_msds'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 font-bold text-[10px] border border-amber-200/70"
                                            title="MSDS / SDS Available">
                                            <span>🛡️</span>
                                            <span>MSDS</span>
                                        </span>
                                    @endif

                                    @if($badgeSummary['other_count'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-neutral-100 text-neutral-700 font-bold text-[10px] border border-neutral-200"
                                            title="{{ $badgeSummary['other_count'] }} Other Document(s)">
                                            <span>📁</span>
                                            <span class="font-mono">+{{ $badgeSummary['other_count'] }}</span>
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>

                        <td class="p-3.5 text-right space-x-1.5 whitespace-nowrap">
                            <button @click="openQuickDocsModal(@js($prod))"
                                class="px-2.5 py-1 text-xs font-bold text-neutral-700 bg-neutral-100 hover:bg-neutral-200 rounded-lg transition-colors cursor-pointer"
                                title="Quick Document Viewer">
                                📁 Docs
                            </button>
                            <button @click="openEditModal(@js($prod))"
                                class="px-2.5 py-1 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                                Edit
                            </button>
                            <form action="{{ route('products.destroy', $prod->id) }}" method="POST" class="inline-block"
                                onsubmit="return confirm('Delete product {{ $prod->product_name }}?');">
                                @csrf
                                <button type="submit"
                                    class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <!-- Quick Documents Side Panel / Modal -->
    <template x-teleport="body">
        <div x-show="isQuickDocsOpen" x-cloak
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-end p-0 sm:p-4">
            <div @click.outside="isQuickDocsOpen = false"
                class="bg-white rounded-none sm:rounded-3xl shadow-2xl border-l sm:border border-neutral-200/80 w-full max-w-xl h-full sm:max-h-[92vh] flex flex-col text-xs overflow-hidden">
                <!-- Panel Header -->
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3 truncate">
                        <div class="w-10 h-10 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold shrink-0">
                            📁
                        </div>
                        <div class="truncate">
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display truncate"
                                x-text="quickProduct ? quickProduct.product_name : 'Product Documents'"></h3>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="font-mono text-[10px] font-bold text-[#D7FF53]"
                                    x-text="quickProduct ? quickProduct.product_code : ''"></span>
                                <span class="text-neutral-400 text-[10px]">•</span>
                                <span class="text-neutral-400 text-[10px]"
                                    x-text="'HSN: ' + (quickProduct ? (quickProduct.hsn_code || 'N/A') : '')"></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="isQuickDocsOpen = false"
                        class="text-neutral-400 hover:text-white p-1 rounded-lg text-sm cursor-pointer">✕</button>
                </div>

                <!-- Panel Action Bar -->
                <div class="p-3 bg-neutral-50 border-b border-neutral-200 flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="openEditModal(quickProduct, 'docs')"
                            class="px-3 py-1.5 bg-neutral-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                            <span>📤</span>
                            <span>Upload / Manage</span>
                        </button>
                        <button type="button" @click="openShareModal(quickProduct)"
                            class="px-3 py-1.5 bg-[#D7FF53] hover:bg-[#c8f043] text-[#091315] font-extrabold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5 border border-[#c8f043] cursor-pointer">
                            <span>🔗</span>
                            <span>Share Link</span>
                        </button>
                    </div>

                    <template x-if="docList.photos.length > 0 || docList.documents.length > 0">
                        <a :href="'/products/' + (quickProduct ? quickProduct.id : '') + '/documents/download-all'"
                            class="px-2.5 py-1.5 text-neutral-700 hover:text-neutral-900 font-bold text-xs flex items-center gap-1 hover:underline">
                            <span>⬇</span>
                            <span>ZIP All</span>
                        </a>
                    </template>
                </div>

                <!-- Panel Content -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-6 touch-scroll">
                    <!-- Loading state -->
                    <div x-show="isLoadingDocs" class="py-12 text-center space-y-2">
                        <div class="w-8 h-8 border-3 border-neutral-300 border-t-[#091315] rounded-full animate-spin mx-auto"></div>
                        <p class="text-xs text-neutral-500 font-medium">Loading product assets...</p>
                    </div>

                    <div x-show="!isLoadingDocs">
                        <!-- Photos Section -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-neutral-900 text-xs flex items-center gap-1.5 uppercase tracking-wider font-mono">
                                    <span>📸</span> Photos (<span x-text="docList.photos.length"></span>)
                                </h4>
                            </div>

                            <template x-if="docList.photos.length === 0">
                                <div class="p-4 rounded-2xl bg-neutral-50 border border-neutral-200 text-center text-neutral-400 text-xs">
                                    No photos uploaded yet.
                                </div>
                            </template>

                            <div class="grid grid-cols-3 gap-2.5" x-show="docList.photos.length > 0">
                                <template x-for="photo in docList.photos" :key="photo.id">
                                    <div class="relative group bg-neutral-100 rounded-xl overflow-hidden border border-neutral-200 aspect-square flex flex-col justify-end">
                                        <img :src="photo.preview_url" :alt="photo.file_name"
                                            class="absolute inset-0 w-full h-full object-cover">
                                        <template x-if="photo.is_primary">
                                            <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-md bg-[#091315] text-[#D7FF53] text-[9px] font-black font-mono shadow-xs">
                                                ★ Primary
                                            </span>
                                        </template>
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                            <button type="button" @click="openPreview(photo.preview_url, photo.file_name, photo.mime_type)"
                                                class="p-1.5 bg-white text-neutral-800 rounded-lg text-xs font-bold shadow-xs hover:bg-neutral-100 cursor-pointer" title="Preview">
                                                👁️
                                            </button>
                                            <a :href="photo.download_url"
                                                class="p-1.5 bg-[#D7FF53] text-[#091315] rounded-lg text-xs font-bold shadow-xs hover:bg-[#c8f043]" title="Download">
                                                ⬇
                                            </a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Technical Documents Section -->
                        <div class="space-y-3 mt-6 pt-6 border-t border-neutral-100">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-neutral-900 text-xs flex items-center gap-1.5 uppercase tracking-wider font-mono">
                                    <span>📑</span> Technical Documents & Certificates (<span x-text="docList.documents.length"></span>)
                                </h4>
                            </div>

                            <template x-if="docList.documents.length === 0">
                                <div class="p-4 rounded-2xl bg-neutral-50 border border-neutral-200 text-center text-neutral-400 text-xs">
                                    No technical documents uploaded yet.
                                </div>
                            </template>

                            <div class="space-y-2" x-show="docList.documents.length > 0">
                                <template x-for="doc in docList.documents" :key="doc.id">
                                    <div class="p-3 rounded-2xl bg-neutral-50 hover:bg-neutral-100/80 border border-neutral-200/80 transition-colors flex items-center justify-between gap-3">
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                    :class="doc.type_badge_class">
                                                    <span x-text="doc.type_icon"></span>
                                                    <span x-text="doc.type_label"></span>
                                                </span>
                                                <template x-if="doc.is_latest">
                                                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-black font-mono uppercase">
                                                        Latest
                                                    </span>
                                                </template>
                                                <template x-if="doc.version">
                                                    <span class="text-[10px] font-mono text-neutral-500 font-bold" x-text="'v' + doc.version"></span>
                                                </template>
                                            </div>

                                            <div class="font-bold text-neutral-900 truncate" x-text="doc.file_name"></div>

                                            <div class="flex items-center gap-2 text-[10px] text-neutral-500">
                                                <span x-text="doc.formatted_file_size"></span>
                                                <span>•</span>
                                                <template x-if="doc.valid_until">
                                                    <span :class="doc.is_expired ? 'text-rose-600 font-bold' : (doc.days_until_expiry <= 30 ? 'text-amber-600 font-bold' : 'text-neutral-500')"
                                                        x-text="doc.is_expired ? 'Expired' : 'Valid until ' + doc.valid_until"></span>
                                                </template>
                                                <template x-if="!doc.valid_until">
                                                    <span>No expiry</span>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button" @click="openPreview(doc.preview_url, doc.file_name, doc.mime_type)"
                                                class="px-2.5 py-1 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-100 text-neutral-700 font-bold text-xs cursor-pointer">
                                                👁️
                                            </button>
                                            <a :href="doc.download_url"
                                                class="px-2.5 py-1 rounded-lg bg-[#091315] hover:bg-black text-[#D7FF53] font-bold text-xs cursor-pointer">
                                                ⬇
                                            </a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Active Shares Section -->
                        <template x-if="docList.shares && docList.shares.length > 0">
                            <div class="space-y-3 mt-6 pt-6 border-t border-neutral-100">
                                <h4 class="font-extrabold text-neutral-900 text-xs flex items-center gap-1.5 uppercase tracking-wider font-mono">
                                    <span>🔗</span> Active Public Share Links (<span x-text="docList.shares.length"></span>)
                                </h4>

                                <div class="space-y-2">
                                    <template x-for="share in docList.shares" :key="share.id">
                                        <div class="p-2.5 rounded-xl bg-white border border-neutral-200 flex items-center justify-between gap-2 text-[11px]">
                                            <div class="truncate">
                                                <div class="font-bold text-neutral-800 truncate" x-text="share.title"></div>
                                                <div class="text-[10px] text-neutral-400 font-mono" x-text="'Expires: ' + share.expires_at_formatted + ' • Views: ' + share.views_count"></div>
                                            </div>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <button type="button" @click="copyShareUrl(share.share_url)"
                                                    class="px-2 py-1 rounded-lg bg-neutral-100 hover:bg-neutral-200 font-bold text-[10px] text-neutral-700 cursor-pointer">
                                                    📋 Copy
                                                </button>
                                                <a :href="share.share_url" target="_blank"
                                                    class="px-2 py-1 rounded-lg bg-neutral-100 hover:bg-neutral-200 font-bold text-[10px] text-neutral-700">
                                                    ↗ Open
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Datalist of Standard Dehydration Cuts for Autocomplete -->
    <datalist id="standardCutsList">
        <option value="Dehydrated Garlic Flakes (A-Grade)"></option>
        <option value="Dehydrated Garlic Chopped (3-5 mm)"></option>
        <option value="Dehydrated Garlic Minced (1-3 mm)"></option>
        <option value="Dehydrated Garlic Granules (40-60 Mesh)"></option>
        <option value="Dehydrated Garlic Powder (80-100 Mesh)"></option>
        <option value="Dehydrated White Onion Flakes / Kibbled"></option>
        <option value="Dehydrated White Onion Chopped (3-5 mm)"></option>
        <option value="Dehydrated White Onion Minced (1-3 mm)"></option>
        <option value="Dehydrated White Onion Granules (40-60 Mesh)"></option>
        <option value="Dehydrated White Onion Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Red Onion Flakes / Kibbled"></option>
        <option value="Dehydrated Red Onion Chopped (3-5 mm)"></option>
        <option value="Dehydrated Red Onion Minced (1-3 mm)"></option>
        <option value="Dehydrated Red Onion Granules (40-60 Mesh)"></option>
        <option value="Dehydrated Red Onion Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Pink Onion Flakes / Kibbled"></option>
        <option value="Dehydrated Pink Onion Chopped (3-5 mm)"></option>
        <option value="Dehydrated Pink Onion Minced (1-3 mm)"></option>
        <option value="Dehydrated Pink Onion Granules (40-60 Mesh)"></option>
        <option value="Dehydrated Pink Onion Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Spring Onion Flakes"></option>
        <option value="Dehydrated Spring Onion Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Cabbage Flakes (3-5 mm)"></option>
        <option value="Dehydrated Cabbage Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Carrot Flakes (3-5 mm)"></option>
        <option value="Dehydrated Carrot Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Beetroot Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Beetroot Flakes (3-5 mm)"></option>
        <option value="Dehydrated Spinach Powder (Palak Powder)"></option>
        <option value="Dehydrated Tomato Powder"></option>
        <option value="Dehydrated Ginger Flakes"></option>
        <option value="Dehydrated Ginger Powder (Sonth Powder)"></option>
        <option value="Dehydrated Kasuri Methi (Fenugreek Leaves)"></option>
        <option value="Dehydrated Toasted White Onion Flakes"></option>
        <option value="Dehydrated Toasted White Onion Powder (80-100 Mesh)"></option>
        <option value="Dehydrated Toasted Garlic Powder (80-100 Mesh)"></option>
        <option value="Fried Crispy Garlic Flakes"></option>
        <option value="Fried Crispy Onion (Biryani Cut)"></option>
    </datalist>

    <!-- Add Product Modal -->
    <template x-teleport="body">
        <div x-show="showAddModal" x-cloak
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="showAddModal = false"
                class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-xl w-full overflow-hidden max-h-[92vh] flex flex-col text-xs">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold">
                            📦
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Add Catalog Product</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Standard Export Cut & Packaging</p>
                        </div>
                    </div>
                    <button type="button" @click="showAddModal = false"
                        class="text-neutral-400 hover:text-white p-1 rounded-lg text-sm cursor-pointer">✕</button>
                </div>

                <form action="{{ route('products.store') }}" method="POST"
                    class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto flex-1 touch-scroll">
                    @csrf
                    <!-- Quick Presets -->
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1.5">Quick Pick Industry Standard Cut:</label>
                        <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto p-1.5 bg-neutral-50 rounded-xl border border-neutral-200 touch-scroll">
                            <template x-for="cut in quickCutPresets" :key="cut.code">
                                <button type="button" @click="selectPreset(cut)"
                                    class="px-2 py-1 rounded-lg bg-white border border-neutral-200 text-[10px] font-bold text-neutral-700 hover:bg-[#D7FF53] hover:text-[#091315] hover:border-transparent transition-all cursor-pointer shadow-2xs"
                                    x-text="cut.label"></button>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-neutral-700 mb-1">Product Description & Cut *</label>
                            <input type="text" name="product_name" x-model="form.product_name"
                                @input="onProductNameInput()" list="standardCutsList" required
                                placeholder="e.g. Dehydrated Garlic Powder (80-100 Mesh)"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Export SKU Code *</label>
                            <input type="text" name="product_code" x-model="form.product_code" required
                                placeholder="e.g. DGP-100"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-mono font-bold uppercase focus:ring-2 focus:ring-[#091315]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="relative">
                            <label class="block font-bold text-neutral-700 mb-1">HSN Code (GST Tariff) *</label>
                            <input type="text" name="hsn_code" x-model="form.hsn_code" required
                                @focus="isHsnDropdownOpen = true"
                                placeholder="e.g. 07129020"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-mono font-bold focus:ring-2 focus:ring-[#091315]">
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Market Reference Rate / KG (₹) *</label>
                            <input type="number" step="0.01" name="standard_rate" x-model="form.standard_rate" required
                                placeholder="e.g. 140.00"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">GST Tax Rate (%) *</label>
                            <input type="number" step="0.01" name="tax_rate_percent" x-model="form.tax_rate_percent" required
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Default Packaging</label>
                            <input type="text" name="packaging" x-model="form.packaging"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315]">
                        </div>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2 shrink-0">
                        <button type="button" @click="showAddModal = false"
                            class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-full cursor-pointer">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs cursor-pointer border border-[#c8f043]">
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Comprehensive Product Edit Modal with TAB 1 (Master) & TAB 2 (Documents & Media) -->
    <template x-teleport="body">
        <div x-show="isEditModalOpen" x-cloak
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isEditModalOpen = false"
                class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-2xl w-full overflow-hidden max-h-[92vh] flex flex-col text-xs">
                
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold">
                            ✏️
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Product Master</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono"
                                x-text="'SKU: ' + (editProduct ? editProduct.product_code : '') + ' • ' + (editProduct ? editProduct.product_name : '')"></p>
                        </div>
                    </div>
                    <button type="button" @click="isEditModalOpen = false"
                        class="text-neutral-400 hover:text-white p-1 rounded-lg text-sm cursor-pointer">✕</button>
                </div>

                <!-- Tab Navigation Header -->
                <div class="px-5 pt-3 bg-neutral-50 border-b border-neutral-200 flex items-center gap-2 shrink-0">
                    <button type="button" @click="activeEditTab = 'master'"
                        class="px-4 py-2 font-bold text-xs rounded-t-xl transition-colors cursor-pointer flex items-center gap-1.5"
                        :class="activeEditTab === 'master' ? 'bg-white text-neutral-900 border-t border-x border-neutral-200 shadow-2xs font-extrabold' : 'text-neutral-500 hover:text-neutral-800'">
                        <span>📦</span>
                        <span>Product Master</span>
                    </button>
                    <button type="button" @click="activeEditTab = 'docs'"
                        class="px-4 py-2 font-bold text-xs rounded-t-xl transition-colors cursor-pointer flex items-center gap-1.5"
                        :class="activeEditTab === 'docs' ? 'bg-white text-neutral-900 border-t border-x border-neutral-200 shadow-2xs font-extrabold' : 'text-neutral-500 hover:text-neutral-800'">
                        <span>📁</span>
                        <span>Documents & Media</span>
                        <span class="px-1.5 py-0.2 rounded-full bg-neutral-200 text-neutral-700 text-[10px] font-mono"
                            x-text="docList.photos.length + docList.documents.length"></span>
                    </button>
                </div>

                <!-- TAB 1: Product Master Fields Form -->
                <div x-show="activeEditTab === 'master'" class="flex-1 overflow-y-auto p-4 sm:p-5 flex flex-col justify-between">
                    <form :action="'/products/' + (editProduct ? editProduct.id : '') + '/update'" method="POST"
                        class="space-y-3.5 text-xs">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-neutral-700 mb-1">Product Description & Cut *</label>
                                <input type="text" name="product_name" x-model="editProduct.product_name" required
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Export SKU Code</label>
                                <input type="text" name="product_code" x-model="editProduct.product_code"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-mono font-bold uppercase focus:ring-2 focus:ring-[#091315]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">HSN Code (GST Tariff)</label>
                                <input type="text" name="hsn_code" x-model="editProduct.hsn_code"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-mono font-bold focus:ring-2 focus:ring-[#091315]">
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Market Reference Rate / KG (₹) *</label>
                                <input type="number" step="0.01" name="standard_rate" x-model="editProduct.standard_rate" required
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">GST Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate_percent" x-model="editProduct.tax_rate_percent"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Packaging Spec</label>
                                <input type="text" name="packaging" x-model="editProduct.packaging"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315]">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-neutral-100 flex justify-end gap-2 shrink-0">
                            <button type="button" @click="isEditModalOpen = false"
                                class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-full cursor-pointer">Cancel</button>
                            <button type="submit"
                                class="px-5 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs cursor-pointer border border-[#c8f043]">
                                Update Product
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: Documents & Media Management (Photos + Docs) -->
                <div x-show="activeEditTab === 'docs'" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-6 touch-scroll">
                    
                    <!-- Top Action Bar for Documents Tab -->
                    <div class="flex items-center justify-between gap-3 p-3 bg-neutral-50 rounded-2xl border border-neutral-200">
                        <div class="text-xs text-neutral-600 font-medium">
                            Manage quality inspection photos, certificates of analysis, technical specifications, and MSDS.
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" @click="openShareModal(editProduct)"
                                class="px-3 py-1.5 bg-[#091315] hover:bg-black text-[#D7FF53] font-black text-xs rounded-full shadow-2xs flex items-center gap-1.5 cursor-pointer border border-neutral-800">
                                <span>🔗</span>
                                <span>Share Documents</span>
                            </button>
                        </div>
                    </div>

                    <!-- SECTION A: PHOTOS -->
                    <div class="bg-neutral-50/70 p-4 rounded-2xl border border-neutral-200/80 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-extrabold text-neutral-900 text-xs flex items-center gap-2">
                                    <span>📸</span>
                                    <span>Product Photos</span>
                                    <span class="font-mono text-[10px] text-neutral-500 font-bold" x-text="'(' + docList.photos.length + ')'"></span>
                                </h4>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Physical cuts, mesh inspection, packaging images</p>
                            </div>
                        </div>

                        <!-- Photo Upload Dropzone -->
                        <div class="relative border-2 border-dashed border-neutral-300 hover:border-[#091315] rounded-2xl p-4 text-center bg-white transition-colors cursor-pointer group"
                            @dragover.prevent=""
                            @drop.prevent="handlePhotoDrop($event)">
                            <input type="file" multiple accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                @change="handlePhotoSelect($event)">
                            <div class="space-y-1">
                                <span class="text-2xl block">🖼️</span>
                                <p class="text-xs font-bold text-neutral-700">Drag & drop photos here, or <span class="text-blue-600 underline">browse</span></p>
                                <p class="text-[10px] text-neutral-400 font-mono">PNG, JPG, WEBP up to 20MB per photo</p>
                            </div>
                            <div x-show="isUploadingPhotos" class="absolute inset-0 bg-white/90 flex items-center justify-center gap-2">
                                <div class="w-4 h-4 border-2 border-neutral-400 border-t-[#091315] rounded-full animate-spin"></div>
                                <span class="text-xs font-bold text-neutral-800">Uploading photos...</span>
                            </div>
                        </div>

                        <!-- Photos Grid with Reorder, Primary Star, Preview, Delete -->
                        <template x-if="docList.photos.length === 0">
                            <div class="text-center py-4 text-neutral-400 text-xs italic">
                                No photos uploaded for this product yet.
                            </div>
                        </template>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" x-show="docList.photos.length > 0">
                            <template x-for="(photo, index) in docList.photos" :key="photo.id">
                                <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden group flex flex-col shadow-2xs">
                                    <div class="aspect-square relative bg-neutral-100 flex items-center justify-center">
                                        <img :src="photo.preview_url" :alt="photo.file_name" class="w-full h-full object-cover">
                                        
                                        <!-- Primary Badge / Button -->
                                        <button type="button" @click="setPrimaryPhoto(photo.id)"
                                            class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-md text-[9px] font-black uppercase font-mono shadow-xs transition-colors cursor-pointer"
                                            :class="photo.is_primary ? 'bg-[#091315] text-[#D7FF53] border border-[#D7FF53]/40' : 'bg-black/60 text-white hover:bg-black/80'">
                                            <span x-text="photo.is_primary ? '★ Primary' : '☆ Set Primary'"></span>
                                        </button>

                                        <!-- Hover Controls -->
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5">
                                            <button type="button" @click="openPreview(photo.preview_url, photo.file_name, photo.mime_type)"
                                                class="p-1.5 bg-white text-neutral-800 rounded-lg text-xs font-bold shadow-xs hover:bg-neutral-100 cursor-pointer" title="Preview">
                                                👁️
                                            </button>
                                            <a :href="photo.download_url"
                                                class="p-1.5 bg-[#D7FF53] text-[#091315] rounded-lg text-xs font-bold shadow-xs hover:bg-[#c8f043]" title="Download">
                                                ⬇
                                            </a>
                                            <button type="button" @click="deleteDoc(photo.id)"
                                                class="p-1.5 bg-rose-600 text-white rounded-lg text-xs font-bold shadow-xs hover:bg-rose-700 cursor-pointer" title="Delete">
                                                🗑
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Reorder Controls & Filename -->
                                    <div class="p-2 border-t border-neutral-100 flex items-center justify-between gap-1 text-[11px]">
                                        <span class="truncate font-bold text-neutral-700 text-[10px]" x-text="photo.file_name"></span>
                                        <div class="flex items-center gap-0.5 shrink-0">
                                            <button type="button" @click="movePhoto(photo.id, 'up')" :disabled="index === 0"
                                                class="px-1 py-0.5 text-[9px] bg-neutral-100 hover:bg-neutral-200 disabled:opacity-30 rounded cursor-pointer">
                                                ◀
                                            </button>
                                            <button type="button" @click="movePhoto(photo.id, 'down')" :disabled="index === docList.photos.length - 1"
                                                class="px-1 py-0.5 text-[9px] bg-neutral-100 hover:bg-neutral-200 disabled:opacity-30 rounded cursor-pointer">
                                                ▶
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- SECTION B: DOCUMENTS (COA, SPEC SHEET, MSDS, OTHER) -->
                    <div class="bg-neutral-50/70 p-4 rounded-2xl border border-neutral-200/80 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-extrabold text-neutral-900 text-xs flex items-center gap-2">
                                    <span>📑</span>
                                    <span>Certificates & Technical Documents</span>
                                    <span class="font-mono text-[10px] text-neutral-500 font-bold" x-text="'(' + docList.documents.length + ')'"></span>
                                </h4>
                                <p class="text-[11px] text-neutral-500 mt-0.5">COA, Technical Data Sheets, MSDS, Quality Lab Reports</p>
                            </div>
                        </div>

                        <!-- Document Upload Form (Drag & Drop or Manual) -->
                        <form @submit.prevent="submitDocumentUpload()" class="p-3.5 bg-white rounded-2xl border border-neutral-200 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div>
                                    <label class="block font-bold text-neutral-700 mb-1 text-[11px]">Document Type *</label>
                                    <select x-model="docUploadForm.document_type" required
                                        class="w-full px-2.5 py-1.5 border border-neutral-300 rounded-xl font-bold bg-neutral-50 text-xs focus:ring-2 focus:ring-[#091315]">
                                        <option value="coa">📜 COA (Certificate of Analysis)</option>
                                        <option value="specification_sheet">📋 Specification Sheet / TDS</option>
                                        <option value="msds">🛡️ MSDS / SDS Safety Sheet</option>
                                        <option value="other">📁 Other Compliance Document</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-bold text-neutral-700 mb-1 text-[11px]">Choose File * (PDF, DOC, DOCX, XLS, XLSX)</label>
                                    <input type="file" required id="docFileInput"
                                        @change="onDocFileChange($event)"
                                        class="w-full text-xs text-neutral-500 file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#091315] file:text-[#D7FF53] hover:file:bg-black cursor-pointer">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div>
                                    <label class="block font-bold text-neutral-700 mb-1 text-[11px]">Version (Optional)</label>
                                    <input type="text" x-model="docUploadForm.version" placeholder="e.g. v2.1 or 2026-Q1"
                                        class="w-full px-2.5 py-1.5 border border-neutral-300 rounded-xl font-mono text-xs focus:ring-2 focus:ring-[#091315]">
                                </div>
                                <div>
                                    <label class="block font-bold text-neutral-700 mb-1 text-[11px]">Valid Until / Expiry Date</label>
                                    <input type="date" x-model="docUploadForm.valid_until"
                                        class="w-full px-2.5 py-1.5 border border-neutral-300 rounded-xl text-xs focus:ring-2 focus:ring-[#091315]">
                                </div>
                                <div>
                                    <label class="block font-bold text-neutral-700 mb-1 text-[11px]">Display File Name (Optional)</label>
                                    <input type="text" x-model="docUploadForm.file_name" placeholder="Custom name..."
                                        class="w-full px-2.5 py-1.5 border border-neutral-300 rounded-xl text-xs focus:ring-2 focus:ring-[#091315]">
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" x-model="docUploadForm.is_latest" class="rounded text-[#091315] focus:ring-[#091315]">
                                    <span class="font-bold text-neutral-800 text-[11px]">Mark as "Latest" version of this document type</span>
                                </label>

                                <button type="submit" :disabled="isUploadingDoc"
                                    class="px-4 py-2 bg-[#091315] hover:bg-black text-[#D7FF53] font-black text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50">
                                    <span x-show="!isUploadingDoc">⬆ Upload Document</span>
                                    <span x-show="isUploadingDoc">Uploading...</span>
                                </button>
                            </div>
                        </form>

                        <!-- Existing Documents List -->
                        <template x-if="docList.documents.length === 0">
                            <div class="text-center py-4 text-neutral-400 text-xs italic">
                                No technical documents or certificates uploaded yet.
                            </div>
                        </template>

                        <div class="space-y-2" x-show="docList.documents.length > 0">
                            <template x-for="doc in docList.documents" :key="doc.id">
                                <div class="p-3 bg-white rounded-2xl border border-neutral-200 flex items-center justify-between gap-3">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                :class="doc.type_badge_class">
                                                <span x-text="doc.type_icon"></span>
                                                <span x-text="doc.type_label"></span>
                                            </span>
                                            <button type="button" @click="toggleLatest(doc.id)"
                                                class="px-1.5 py-0.5 rounded text-[9px] font-black font-mono uppercase transition-colors cursor-pointer"
                                                :class="doc.is_latest ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'">
                                                <span x-text="doc.is_latest ? '✓ Latest' : 'Set Latest'"></span>
                                            </button>
                                            <template x-if="doc.version">
                                                <span class="text-[10px] font-mono text-neutral-500 font-bold" x-text="'v' + doc.version"></span>
                                            </template>
                                        </div>

                                        <div class="font-bold text-neutral-900 truncate" x-text="doc.file_name"></div>

                                        <div class="flex items-center gap-2 text-[10px] text-neutral-500">
                                            <span x-text="doc.formatted_file_size"></span>
                                            <span>•</span>
                                            <template x-if="doc.valid_until">
                                                <span :class="doc.is_expired ? 'text-rose-600 font-bold' : (doc.days_until_expiry <= 30 ? 'text-amber-600 font-bold' : 'text-neutral-500')"
                                                    x-text="doc.is_expired ? 'Expired (' + doc.valid_until + ')' : 'Valid to ' + doc.valid_until"></span>
                                            </template>
                                            <template x-if="!doc.valid_until">
                                                <span>No expiry date</span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" @click="openPreview(doc.preview_url, doc.file_name, doc.mime_type)"
                                            class="p-1.5 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-xs cursor-pointer" title="Preview">
                                            👁️
                                        </button>
                                        <a :href="doc.download_url"
                                            class="p-1.5 rounded-lg bg-[#091315] hover:bg-black text-[#D7FF53] text-xs cursor-pointer" title="Download">
                                            ⬇
                                        </a>
                                        <button type="button" @click="deleteDoc(doc.id)"
                                            class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs cursor-pointer" title="Delete">
                                            🗑
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Share Documents Modal Dialog -->
    <template x-teleport="body">
        <div x-show="isShareModalOpen" x-cloak
            class="fixed inset-0 z-[110] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isShareModalOpen = false"
                class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 max-w-lg w-full overflow-hidden max-h-[92vh] flex flex-col text-xs">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold">
                            🔗
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Share Product Documents</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Secure, time-limited customer portal link</p>
                        </div>
                    </div>
                    <button type="button" @click="isShareModalOpen = false"
                        class="text-neutral-400 hover:text-white p-1 rounded-lg text-sm cursor-pointer">✕</button>
                </div>

                <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 touch-scroll">
                    <!-- Link generation form -->
                    <form @submit.prevent="generateShareLink()" class="space-y-3.5">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Share Title</label>
                            <input type="text" x-model="shareForm.title" required
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold focus:ring-2 focus:ring-[#091315]">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Link Expiry</label>
                                <select x-model="shareForm.expires_in"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-bold bg-neutral-50 focus:ring-2 focus:ring-[#091315]">
                                    <option value="24h">24 Hours (1 Day)</option>
                                    <option value="7d" selected>7 Days (Standard)</option>
                                    <option value="30d">30 Days</option>
                                    <option value="90d">90 Days</option>
                                    <option value="never">Never (Permanent)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Optional PIN / Password</label>
                                <input type="password" x-model="shareForm.password" placeholder="Leave empty for public"
                                    class="w-full px-3 py-2 border border-neutral-300 rounded-xl font-mono focus:ring-2 focus:ring-[#091315]">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Buyer / Recipient Email (Optional)</label>
                            <input type="email" x-model="shareForm.recipient_email" placeholder="buyer@client.com"
                                class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315]">
                        </div>

                        <!-- Document Selector Checkboxes -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="font-bold text-neutral-700">Select Assets to Include:</label>
                                <button type="button" @click="toggleSelectAllDocs()" class="text-blue-600 font-bold hover:underline text-[11px]">
                                    <span x-text="isAllDocsSelected ? 'Deselect All' : 'Select All'"></span>
                                </button>
                            </div>

                            <div class="max-h-36 overflow-y-auto p-2 bg-neutral-50 rounded-2xl border border-neutral-200 space-y-1.5 touch-scroll">
                                <template x-for="item in allAvailableAssets" :key="item.id">
                                    <label class="flex items-center gap-2 p-1.5 hover:bg-white rounded-lg cursor-pointer transition-colors">
                                        <input type="checkbox" :value="item.id" x-model="shareForm.selected_document_ids"
                                            class="rounded text-[#091315] focus:ring-[#091315]">
                                        <span class="truncate text-[11px] font-medium text-neutral-800" x-text="item.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <button type="submit" :disabled="isGeneratingShare"
                            class="w-full py-2.5 px-4 bg-[#091315] hover:bg-black text-[#D7FF53] font-black text-xs rounded-xl shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <span x-show="!isGeneratingShare">🔗 Generate Secure Share Link</span>
                            <span x-show="isGeneratingShare">Generating...</span>
                        </button>
                    </form>

                    <!-- Newly Generated Share Link Box -->
                    <div x-show="newShareUrl" class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-emerald-900 text-xs flex items-center gap-1.5">
                                <span>✓</span> Share Link Created!
                            </span>
                            <span class="text-[10px] text-emerald-700 font-mono" x-text="'Valid to: ' + newShareExpiry"></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="text" readonly :value="newShareUrl"
                                class="flex-1 px-3 py-2 text-xs font-mono bg-white border border-emerald-300 rounded-xl">
                            <button type="button" @click="copyShareUrl(newShareUrl)"
                                class="px-3 py-2 bg-[#091315] hover:bg-black text-[#D7FF53] font-bold text-xs rounded-xl cursor-pointer shrink-0">
                                <span x-text="copiedFeedback ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <a :href="'mailto:' + (shareForm.recipient_email || '') + '?subject=' + encodeURIComponent('Documentation Dossier: ' + (shareProduct ? shareProduct.product_name : '')) + '&body=' + encodeURIComponent('Please find the product documentation and certificates here: ' + newShareUrl)"
                                class="px-3 py-1.5 bg-white hover:bg-neutral-100 text-neutral-800 font-bold text-xs rounded-xl border border-neutral-300 flex items-center gap-1">
                                ✉ Email Client
                            </a>
                            <a :href="newShareUrl" target="_blank"
                                class="px-3 py-1.5 bg-white hover:bg-neutral-100 text-neutral-800 font-bold text-xs rounded-xl border border-neutral-300 flex items-center gap-1">
                                ↗ Open Public View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Universal Preview Modal -->
    <template x-teleport="body">
        <div x-show="previewModalOpen" x-cloak
            class="fixed inset-0 z-[120] bg-black/80 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="previewModalOpen = false"
                class="bg-white rounded-3xl shadow-2xl border border-neutral-800 w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden">
                <div class="px-5 py-3.5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5 truncate">
                        <span class="text-lg">📄</span>
                        <h3 class="font-bold text-sm text-white truncate font-display" x-text="previewDoc ? previewDoc.name : 'Preview'"></h3>
                    </div>
                    <button type="button" @click="previewModalOpen = false"
                        class="text-neutral-400 hover:text-white p-1 rounded-lg text-lg cursor-pointer">✕</button>
                </div>
                <div class="flex-1 bg-neutral-100 relative overflow-hidden flex items-center justify-center p-2">
                    <template x-if="previewType === 'image'">
                        <img :src="previewDoc.url" :alt="previewDoc.name" class="max-h-full max-w-full object-contain rounded-xl shadow-md">
                    </template>
                    <template x-if="previewType === 'pdf' || previewType === 'frame'">
                        <iframe :src="previewDoc.url" class="w-full h-full rounded-xl border border-neutral-200 bg-white"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function productsManager() {
    return {
        showAddModal: false,
        isEditModalOpen: false,
        activeEditTab: 'master',
        isQuickDocsOpen: false,
        quickProduct: null,
        editProduct: {
            id: '',
            product_name: '',
            product_code: '',
            hsn_code: '07129020',
            standard_rate: '',
            tax_rate_percent: '5.00',
            packaging: ''
        },
        docList: {
            photos: [],
            documents: [],
            shares: []
        },
        isLoadingDocs: false,
        isUploadingPhotos: false,
        isUploadingDoc: false,
        docUploadForm: {
            document_type: 'coa',
            file: null,
            file_name: '',
            version: '',
            valid_until: '',
            is_latest: true,
            notes: ''
        },
        isShareModalOpen: false,
        shareProduct: null,
        shareForm: {
            title: '',
            recipient_email: '',
            expires_in: '7d',
            password: '',
            selected_document_ids: []
        },
        isGeneratingShare: false,
        newShareUrl: '',
        newShareExpiry: '',
        copiedFeedback: false,
        previewModalOpen: false,
        previewDoc: null,
        previewType: '',
        isSearchingHsn: false,
        isHsnDropdownOpen: false,
        hsnSearchResults: [],
        hsnDebounceTimer: null,
        form: {
            product_name: '',
            product_code: '',
            hsn_code: '07129020',
            standard_rate: '',
            tax_rate_percent: '5.00',
            packaging: '20 KGs Two Poly Liner Bags with Laminated Paper Bag'
        },
        quickCutPresets: [
            { label: 'Garlic Flakes (DGF)', name: 'Dehydrated Garlic Flakes (A-Grade)', code: 'DGF', hsn: '07129020', rate: '135.00' },
            { label: 'Garlic Powder (DGP-100)', name: 'Dehydrated Garlic Powder (80-100 Mesh)', code: 'DGP-100', hsn: '07129020', rate: '140.00' },
            { label: 'Garlic Minced (DGM)', name: 'Dehydrated Garlic Minced (1-3 mm)', code: 'DGM', hsn: '07129020', rate: '145.00' },
            { label: 'White Onion Flakes (DWOF)', name: 'Dehydrated White Onion Flakes / Kibbled', code: 'DWOF', hsn: '07122000', rate: '95.00' },
            { label: 'White Onion Powder (DWOP-100)', name: 'Dehydrated White Onion Powder (80-100 Mesh)', code: 'DWOP-100', hsn: '07122000', rate: '173.00' },
            { label: 'White Onion Minced (DWOM)', name: 'Dehydrated White Onion Minced (1-3 mm)', code: 'DWOM', hsn: '07122000', rate: '100.00' },
            { label: 'Pink Onion Powder (DPOP-100)', name: 'Dehydrated Pink Onion Powder (80-100 Mesh)', code: 'DPOP-100', hsn: '07122000', rate: '115.00' },
            { label: 'Toasted White Onion Powder (DTWOP-100)', name: 'Dehydrated Toasted White Onion Powder (80-100 Mesh)', code: 'DTWOP-100', hsn: '07122000', rate: '198.00' },
            { label: 'Toasted White Onion Flakes (DTWOF)', name: 'Dehydrated Toasted White Onion Flakes', code: 'DTWOF', hsn: '07122000', rate: '185.00' },
            { label: 'Spring Onion Flakes (DSOF)', name: 'Dehydrated Spring Onion Flakes', code: 'DSOF', hsn: '07122000', rate: '200.00' },
            { label: 'Cabbage Flakes (DCAB-F-3-5)', name: 'Dehydrated Cabbage Flakes (3-5 mm)', code: 'DCAB-F-3-5', hsn: '07129090', rate: '170.00' },
            { label: 'Carrot Flakes (DCAR-F-3-5)', name: 'Dehydrated Carrot Flakes (3-5 mm)', code: 'DCAR-F-3-5', hsn: '07129090', rate: '165.00' },
            { label: 'Ginger Powder (DGIN-P)', name: 'Dehydrated Ginger Powder (Sonth Powder)', code: 'DGIN-P', hsn: '09101210', rate: '220.00' },
        ],
        get allAvailableAssets() {
            const list = [];
            this.docList.photos.forEach(p => {
                list.push({ id: p.id, label: '📸 Photo: ' + p.file_name });
            });
            this.docList.documents.forEach(d => {
                list.push({ id: d.id, label: d.type_icon + ' ' + d.type_label + ': ' + d.file_name });
            });
            return list;
        },
        get isAllDocsSelected() {
            return this.allAvailableAssets.length > 0 &&
                this.shareForm.selected_document_ids.length === this.allAvailableAssets.length;
        },
        toggleSelectAllDocs() {
            if (this.isAllDocsSelected) {
                this.shareForm.selected_document_ids = [];
            } else {
                this.shareForm.selected_document_ids = this.allAvailableAssets.map(a => a.id);
            }
        },
        openAddModal() {
            this.form = {
                product_name: '',
                product_code: '',
                hsn_code: '07129020',
                standard_rate: '',
                tax_rate_percent: '5.00',
                packaging: '20 KGs Two Poly Liner Bags with Laminated Paper Bag'
            };
            this.showAddModal = true;
            this.searchHsnOnline();
        },
        openEditModal(prod, tab = 'master') {
            this.editProduct = {
                id: prod.id,
                product_name: prod.product_name,
                product_code: prod.product_code,
                hsn_code: prod.hsn_code || '07129090',
                standard_rate: prod.standard_rate || prod.current_rate_per_kg || '',
                tax_rate_percent: (prod.tax_rate !== null && prod.tax_rate !== undefined) ? prod.tax_rate : ((prod.tax_rate_percent !== null && prod.tax_rate_percent !== undefined) ? prod.tax_rate_percent : '5.00'),
                packaging: prod.packaging || prod.default_packaging || '20 KGs Two Poly Liner Bags with Laminated Paper Bag'
            };
            this.activeEditTab = tab;
            this.isQuickDocsOpen = false;
            this.isEditModalOpen = true;
            this.loadProductDocuments(prod.id);
        },
        openQuickDocsModal(prod) {
            this.quickProduct = prod;
            this.isQuickDocsOpen = true;
            this.loadProductDocuments(prod.id);
        },
        async loadProductDocuments(productId) {
            if (!productId) return;
            this.isLoadingDocs = true;
            try {
                const res = await fetch(`/api/products/${productId}/documents`);
                const data = await res.json();
                if (data.success) {
                    this.docList = {
                        photos: data.photos || [],
                        documents: data.documents || [],
                        shares: data.shares || []
                    };
                }
            } catch (err) {
                console.error('Failed to load product documents:', err);
            } finally {
                this.isLoadingDocs = false;
            }
        },
        handlePhotoSelect(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                this.uploadPhotos(files);
            }
        },
        handlePhotoDrop(event) {
            const files = event.dataTransfer.files;
            if (files && files.length > 0) {
                this.uploadPhotos(files);
            }
        },
        async uploadPhotos(files) {
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId) return;

            this.isUploadingPhotos = true;
            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('photos[]', files[i]);
            }

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/photos/upload`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    await this.loadProductDocuments(productId);
                } else {
                    alert(data.message || 'Photo upload failed');
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred during photo upload.');
            } finally {
                this.isUploadingPhotos = false;
            }
        },
        onDocFileChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.docUploadForm.file = file;
                if (!this.docUploadForm.file_name) {
                    this.docUploadForm.file_name = file.name;
                }
            }
        },
        async submitDocumentUpload() {
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId || !this.docUploadForm.file) {
                alert('Please select a file to upload.');
                return;
            }

            this.isUploadingDoc = true;
            const formData = new FormData();
            formData.append('document', this.docUploadForm.file);
            formData.append('document_type', this.docUploadForm.document_type);
            formData.append('file_name', this.docUploadForm.file_name || this.docUploadForm.file.name);
            formData.append('version', this.docUploadForm.version || '');
            formData.append('valid_until', this.docUploadForm.valid_until || '');
            formData.append('is_latest', this.docUploadForm.is_latest ? '1' : '0');
            formData.append('notes', this.docUploadForm.notes || '');

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/documents/upload`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.docUploadForm = {
                        document_type: 'coa',
                        file: null,
                        file_name: '',
                        version: '',
                        valid_until: '',
                        is_latest: true,
                        notes: ''
                    };
                    const fileInput = document.getElementById('docFileInput');
                    if (fileInput) fileInput.value = '';
                    await this.loadProductDocuments(productId);
                } else {
                    alert(data.message || 'Document upload failed');
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred during document upload.');
            } finally {
                this.isUploadingDoc = false;
            }
        },
        async setPrimaryPhoto(photoId) {
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId) return;
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/photos/${photoId}/set-primary`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    await this.loadProductDocuments(productId);
                }
            } catch (err) {
                console.error(err);
            }
        },
        async movePhoto(photoId, direction) {
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId) return;
            const index = this.docList.photos.findIndex(p => p.id === photoId);
            if (index === -1) return;

            const targetIndex = direction === 'up' ? index - 1 : index + 1;
            if (targetIndex < 0 || targetIndex >= this.docList.photos.length) return;

            // Swap in array
            const photos = [...this.docList.photos];
            const temp = photos[index];
            photos[index] = photos[targetIndex];
            photos[targetIndex] = temp;
            this.docList.photos = photos;

            const photoIds = photos.map(p => p.id);
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                await fetch(`/products/${productId}/photos/reorder`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ photo_ids: photoIds })
                });
            } catch (err) {
                console.error(err);
            }
        },
        async toggleLatest(docId) {
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId) return;
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/documents/${docId}/toggle-latest`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    await this.loadProductDocuments(productId);
                }
            } catch (err) {
                console.error(err);
            }
        },
        async deleteDoc(docId) {
            if (!confirm('Are you sure you want to delete this file? This cannot be undone.')) return;
            const productId = this.editProduct ? this.editProduct.id : (this.quickProduct ? this.quickProduct.id : null);
            if (!productId) return;

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/documents/${docId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    await this.loadProductDocuments(productId);
                } else {
                    alert(data.message || 'Delete failed');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to delete file.');
            }
        },
        openShareModal(prod) {
            this.shareProduct = prod;
            this.shareForm = {
                title: `Product Documentation - ${prod.product_code || prod.product_name}`,
                recipient_email: '',
                expires_in: '7d',
                password: '',
                selected_document_ids: this.allAvailableAssets.map(a => a.id)
            };
            this.newShareUrl = '';
            this.isShareModalOpen = true;
        },
        async generateShareLink() {
            const productId = this.shareProduct ? this.shareProduct.id : null;
            if (!productId) return;

            this.isGeneratingShare = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch(`/products/${productId}/documents/share`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.shareForm)
                });
                const data = await res.json();
                if (data.success) {
                    this.newShareUrl = data.share_url;
                    this.newShareExpiry = data.expires_at;
                    await this.loadProductDocuments(productId);
                } else {
                    alert(data.message || 'Failed to create share link');
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred creating the share link.');
            } finally {
                this.isGeneratingShare = false;
            }
        },
        copyShareUrl(url) {
            navigator.clipboard.writeText(url).then(() => {
                this.copiedFeedback = true;
                setTimeout(() => { this.copiedFeedback = false; }, 2000);
            }).catch(() => {
                prompt('Copy this link:', url);
            });
        },
        openPreview(url, name, mime) {
            this.previewDoc = { url, name, mime };
            this.previewType = (mime && mime.includes('pdf')) ? 'pdf' : ((mime && mime.includes('image')) ? 'image' : 'frame');
            this.previewModalOpen = true;
        },
        selectPreset(preset) {
            this.form.product_name = preset.name;
            this.form.product_code = preset.code;
            this.form.hsn_code = preset.hsn;
            this.form.standard_rate = preset.rate;
            this.form.tax_rate_percent = '5.00';
            this.isHsnDropdownOpen = false;
        },
        onProductNameInput() {
            const name = (this.form.product_name || '').toLowerCase().trim();
            if (!name) return;
            this.form.product_code = this.generateSkuClient(name);
            clearTimeout(this.hsnDebounceTimer);
            this.hsnDebounceTimer = setTimeout(() => {
                this.searchHsnOnline(name);
            }, 250);
        },
        async searchHsnOnline(query = '') {
            const q = query || this.form.hsn_code || this.form.product_name || '';
            this.isSearchingHsn = true;
            try {
                const res = await fetch(`/api/hsn-lookup?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (data && data.results) {
                    this.hsnSearchResults = data.results;
                    if (data.results.length > 0 && (!this.form.hsn_code || this.form.hsn_code === '07129020' || this.form.hsn_code === '071290' || this.form.hsn_code === '071220')) {
                        const top = data.results[0];
                        this.form.hsn_code = top.hsn_code;
                        this.form.tax_rate_percent = Number(top.gst_rate).toFixed(2);
                    }
                }
            } catch (err) {
                console.error('HSN lookup error:', err);
            } finally {
                this.isSearchingHsn = false;
            }
        },
        selectHsnItem(item) {
            this.form.hsn_code = item.hsn_code;
            this.form.tax_rate_percent = Number(item.gst_rate).toFixed(2);
            this.isHsnDropdownOpen = false;
        },
        generateSkuClient(name) {
            let sku = 'D';
            if (name.includes('toasted') || name.includes('toast')) sku += 'T';
            else if (name.includes('fried')) sku += 'F';

            if (name.includes('spring onion')) sku += 'SO';
            else if (name.includes('white onion') || (name.includes('white') && name.includes('onion'))) sku += 'WO';
            else if (name.includes('pink onion') || (name.includes('pink') && name.includes('onion'))) sku += 'PO';
            else if (name.includes('red onion') || (name.includes('red') && name.includes('onion'))) sku += 'RO';
            else if (name.includes('garlic')) sku += 'G';
            else if (name.includes('onion')) sku += 'O';
            else if (name.includes('cabbage')) sku = 'DCAB';
            else if (name.includes('carrot')) sku = 'DCAR';
            else if (name.includes('beet')) sku = 'DBEET';
            else if (name.includes('spinach') || name.includes('palak')) sku = 'DSPIN';
            else if (name.includes('tomato')) sku = 'DTOM';
            else if (name.includes('ginger') || name.includes('sonth')) sku = 'DGIN';
            else if (name.includes('green chilli') || name.includes('chilli')) sku = 'DGC';
            else if (name.includes('kasuri methi') || name.includes('methi')) sku = 'DKM';
            else if (name.includes('coriander') || name.includes('dhaniya')) sku = 'DCOR';
            else if (name.includes('mint') || name.includes('pudina')) sku = 'DMINT';

            const isVegetablePrefix = ['DCAB', 'DCAR', 'DBEET', 'DSPIN', 'DTOM', 'DGIN', 'DGC', 'DKM', 'DCOR', 'DMINT'].includes(sku);

            if (name.includes('powder')) {
                sku += isVegetablePrefix ? '-P' : 'P';
            } else if (name.includes('flake') || name.includes('kibbled') || name.includes('clove') || name.includes('leaves')) {
                sku += isVegetablePrefix ? '-F' : 'F';
            } else if (name.includes('minced')) {
                sku += isVegetablePrefix ? '-M' : 'M';
            } else if (name.includes('chopped')) {
                sku += isVegetablePrefix ? '-C' : 'C';
            } else if (name.includes('granule')) {
                sku += isVegetablePrefix ? '-G' : 'G';
            }

            const meshMatch = name.match(/(100|80|60|40)\s*mesh/i);
            const mmMatch = name.match(/([0-9]+)\s*-\s*([0-9]+)\s*mm/i);

            if (meshMatch) {
                sku += '-' + meshMatch[1];
            } else if (mmMatch) {
                sku += '-' + mmMatch[1] + '-' + mmMatch[2];
            } else if (name.includes('100')) {
                sku += '-100';
            }

            return sku.toUpperCase();
        }
    };
}
</script>
@endsection