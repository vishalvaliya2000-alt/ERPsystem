@extends('layouts.app')

@section('title', $product->product_code . ' — ' . $product->product_name)

@section('content')
<div class="space-y-6 pb-12" x-data="{
    activeTab: 'documents',
    previewModalOpen: false,
    previewDoc: null,
    previewType: '',
    shareModalOpen: false,
    openPreview(url, name, mime) {
        this.previewDoc = { url, name, mime };
        this.previewType = (mime && mime.includes('pdf')) ? 'pdf' : ((mime && mime.includes('image')) ? 'image' : 'frame');
        this.previewModalOpen = true;
    }
}">
    <!-- Breadcrumb & Navigation -->
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-neutral-500 font-medium">
            <a href="{{ route('products.index') }}" class="px-3 py-1.5 rounded-full bg-white hover:bg-neutral-100 text-neutral-700 font-bold border border-neutral-200/80 shadow-2xs transition-all flex items-center gap-1.5">
                <span>←</span>
                <span>Products Catalog</span>
            </a>
            <span class="text-neutral-300">/</span>
            <span class="text-neutral-900 font-bold font-mono">{{ $product->product_code }}</span>
        </div>

        <div class="flex items-center gap-2">
            @if($product->documents->count() > 0)
            <a href="{{ route('products.documents.download-all', $product->id) }}"
                class="px-3.5 py-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-full transition-all flex items-center gap-1.5 border border-neutral-200">
                <span>📦</span>
                <span>Download All (ZIP)</span>
            </a>
            @endif
            <button @click="shareModalOpen = true"
                class="px-4 py-1.5 bg-[#091315] hover:bg-black text-[#D7FF53] text-xs font-black rounded-full shadow-2xs transition-all flex items-center gap-1.5 border border-neutral-800 cursor-pointer">
                <span>🔗</span>
                <span>Share Documents</span>
            </button>
        </div>
    </div>

    <!-- Product Summary Card -->
    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-start gap-4">
                @php
                    $primaryPhoto = $product->primaryPhoto();
                @endphp
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-neutral-100 border border-neutral-200 overflow-hidden flex items-center justify-center shrink-0">
                    @if($primaryPhoto)
                        <img src="{{ route('products.documents.preview', ['productId' => $product->id, 'docId' => $primaryPhoto->id]) }}"
                            alt="{{ $product->product_name }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-3xl">📦</span>
                    @endif
                </div>

                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="font-mono text-xs font-black px-2.5 py-0.5 rounded-lg bg-neutral-100 text-neutral-800 border border-neutral-200">
                            {{ $product->product_code }}
                        </span>
                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $product->category?->name ?? 'Product' }}
                        </span>
                        @if($product->hsn_code)
                        <span class="font-mono text-xs text-neutral-500">
                            HSN: <b class="text-neutral-800">{{ $product->hsn_code }}</b>
                        </span>
                        @endif
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-neutral-900 font-display tracking-tight">
                        {{ $product->product_name }}
                    </h1>
                    <p class="text-xs text-neutral-500">
                        {{ $product->packaging ?: 'Standard export poly lined packaging' }}
                    </p>
                </div>
            </div>

            <!-- Rates & Status -->
            <div class="flex items-center gap-4 border-t md:border-t-0 pt-4 md:pt-0 border-neutral-100">
                <div class="text-right">
                    <span class="text-[10px] text-neutral-400 block font-bold uppercase tracking-wider">Reference Market Rate</span>
                    <span class="text-xl font-black text-neutral-900 font-display">₹{{ number_format($product->standard_rate ?: $product->current_rate_per_kg, 2) }}</span>
                    <span class="text-[11px] text-neutral-400 block font-mono">/ KG (GST {{ rtrim(rtrim(number_format((float) ($product->tax_rate ?? $product->tax_rate_percent ?? 5), 2), '0'), '.') }}%)</span>
                </div>
            </div>
        </div>

        <!-- Document Badges Summary -->
        @php $summary = $product->document_badge_summary; @endphp
        <div class="mt-5 pt-4 border-t border-neutral-100 flex items-center gap-2 flex-wrap text-xs">
            <span class="text-neutral-400 font-medium text-[11px]">Available Assets:</span>
            <span class="px-2.5 py-1 rounded-full font-bold font-mono text-[11px] {{ $summary['photos_count'] > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-neutral-100 text-neutral-400' }}">
                📸 {{ $summary['photos_count'] }} Photo{{ $summary['photos_count'] === 1 ? '' : 's' }}
            </span>
            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] {{ $summary['has_coa'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-100 text-neutral-400' }}">
                📜 COA {{ $summary['has_coa'] ? '✓' : '—' }}
            </span>
            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] {{ $summary['has_specification'] ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-neutral-100 text-neutral-400' }}">
                📋 Spec Sheet {{ $summary['has_specification'] ? '✓' : '—' }}
            </span>
            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] {{ $summary['has_msds'] ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-neutral-100 text-neutral-400' }}">
                🛡️ MSDS {{ $summary['has_msds'] ? '✓' : '—' }}
            </span>
        </div>
    </div>

    <!-- Photos Grid Section -->
    @php
        $photos = $product->documents->where('document_type', \App\Models\ProductDocument::TYPE_PHOTO)->sortBy('sort_order');
        $techDocs = $product->documents->where('document_type', '!=', \App\Models\ProductDocument::TYPE_PHOTO)->sortByDesc('is_latest');
    @endphp

    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">📸</span>
                <div>
                    <h2 class="font-black text-base text-neutral-900 font-display">Product Photos & Inspection Images</h2>
                    <p class="text-[11px] text-neutral-500">Visual quality reference for buyers and production team</p>
                </div>
            </div>
            <a href="{{ route('products.index') }}" class="text-xs text-neutral-500 hover:text-neutral-900 font-bold">Manage in Products →</a>
        </div>

        @if($photos->isEmpty())
        <div class="py-8 text-center text-xs text-neutral-400">
            No product photos uploaded yet. Manage photos in the Products catalog.
        </div>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @foreach($photos as $photo)
            @php
                $pUrl = route('products.documents.preview', ['productId' => $product->id, 'docId' => $photo->id]);
                $dUrl = route('products.documents.download', ['productId' => $product->id, 'docId' => $photo->id]);
            @endphp
            <div class="bg-neutral-50 rounded-2xl border border-neutral-200 overflow-hidden group flex flex-col">
                <div class="aspect-square w-full bg-neutral-100 relative cursor-pointer"
                    @click="openPreview('{{ $pUrl }}', '{{ addslashes($photo->file_name) }}', '{{ $photo->mime_type }}')">
                    <img src="{{ $pUrl }}" alt="{{ $photo->file_name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @if($photo->is_primary)
                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-[#091315] text-[#D7FF53] text-[9px] font-black uppercase font-mono shadow-xs border border-[#D7FF53]/30">
                        ★ Primary
                    </span>
                    @endif
                </div>
                <div class="p-2.5 text-xs flex items-center justify-between gap-1 bg-white border-t border-neutral-100 mt-auto">
                    <div class="truncate">
                        <span class="font-bold text-neutral-800 block truncate text-[11px]">{{ $photo->file_name }}</span>
                        <span class="text-[10px] text-neutral-400 font-mono">{{ $photo->formatted_file_size }}</span>
                    </div>
                    <a href="{{ $dUrl }}" class="p-1.5 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-700 shrink-0">
                        ⬇
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Technical Documents Table Section -->
    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">📑</span>
                <div>
                    <h2 class="font-black text-base text-neutral-900 font-display">Technical Data Sheets & Certificates</h2>
                    <p class="text-[11px] text-neutral-500">COA, Specifications, MSDS, and Compliance documents</p>
                </div>
            </div>
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-neutral-100 text-neutral-700">
                {{ $techDocs->count() }} Files
            </span>
        </div>

        @if($techDocs->isEmpty())
        <div class="py-8 text-center text-xs text-neutral-400">
            No technical documents uploaded yet.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-neutral-50 text-neutral-600 font-bold border-b border-neutral-200/80">
                        <th class="p-3">Type</th>
                        <th class="p-3">Document Name</th>
                        <th class="p-3 text-center">Version</th>
                        <th class="p-3">Validity</th>
                        <th class="p-3 text-right">Size</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach($techDocs as $doc)
                    @php
                        $pUrl = route('products.documents.preview', ['productId' => $product->id, 'docId' => $doc->id]);
                        $dUrl = route('products.documents.download', ['productId' => $product->id, 'docId' => $doc->id]);
                    @endphp
                    <tr class="hover:bg-neutral-50/70 transition-colors">
                        <td class="p-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $doc->type_badge_class }}">
                                <span>{{ $doc->type_icon }}</span>
                                <span>{{ $doc->type_label }}</span>
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="font-bold text-neutral-900 text-sm flex items-center gap-2">
                                <span>{{ $doc->file_name }}</span>
                                @if($doc->is_latest)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase font-mono tracking-wide">
                                    LATEST
                                </span>
                                @endif
                            </div>
                            @if($doc->notes)
                            <div class="text-[11px] text-neutral-500 mt-0.5 italic">{{ $doc->notes }}</div>
                            @endif
                        </td>
                        <td class="p-3 text-center font-mono font-bold text-neutral-700 whitespace-nowrap">
                            {{ $doc->version ?: '—' }}
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            @if($doc->valid_until)
                                @if($doc->is_expired)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-[11px]">
                                    ⚠️ Expired ({{ $doc->valid_until->format('d M Y') }})
                                </span>
                                @elseif($doc->days_until_expiry !== null && $doc->days_until_expiry <= 30)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[11px]">
                                    ⏳ Expires in {{ $doc->days_until_expiry }}d
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 font-bold text-[11px]">
                                    ✓ Valid to {{ $doc->valid_until->format('d M Y') }}
                                </span>
                                @endif
                            @else
                                <span class="text-neutral-400 font-mono text-[11px]">No Expiry</span>
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono text-neutral-500 whitespace-nowrap">
                            {{ $doc->formatted_file_size }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap space-x-1.5">
                            <button type="button"
                                @click="openPreview('{{ $pUrl }}', '{{ addslashes($doc->file_name) }}', '{{ $doc->mime_type }}')"
                                class="px-2.5 py-1 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-800 font-bold text-xs inline-flex items-center gap-1 cursor-pointer">
                                👁️ Preview
                            </button>
                            <a href="{{ $dUrl }}"
                                class="px-2.5 py-1 rounded-lg bg-[#091315] hover:bg-black text-[#D7FF53] font-bold text-xs inline-flex items-center gap-1 cursor-pointer">
                                ⬇ Download
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <!-- Multiple Pricing Tiers / Quantity Slabs Section -->
    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">🏷️</span>
                <div>
                    <h2 class="font-black text-base text-neutral-900 font-display">Multiple Pricing Tiers & Volume Slabs</h2>
                    <p class="text-[11px] text-neutral-500">Tier rates applied automatically when quotation or sales order meets minimum threshold</p>
                </div>
            </div>
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-neutral-100 text-neutral-700">
                {{ count($priceSlabs) }} Slabs Configured
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Existing Slabs List -->
            <div class="lg:col-span-2 overflow-x-auto">
                @if($priceSlabs->isEmpty())
                    <div class="py-6 text-center text-xs text-neutral-400 bg-neutral-50 rounded-2xl border border-neutral-100">
                        No tiered pricing configured. Base standard rate (₹{{ number_format($product->standard_rate, 2) }}) applies for all quantities.
                    </div>
                @else
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-neutral-50 border-b border-neutral-200 text-neutral-400 font-mono uppercase text-[10px]">
                                <th class="p-2.5 pl-3">Tier Name</th>
                                <th class="p-2.5">Min Quantity</th>
                                <th class="p-2.5">Special Rate</th>
                                <th class="p-2.5 text-right pr-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach($priceSlabs as $slab)
                                <tr>
                                    <td class="p-2.5 pl-3 font-bold text-neutral-900">{{ $slab->tier_name }}</td>
                                    <td class="p-2.5 font-mono text-neutral-600">≥ {{ number_format($slab->min_quantity, 0) }} {{ $product->uom ?? 'KG' }}</td>
                                    <td class="p-2.5 font-mono font-black text-emerald-700">₹{{ number_format($slab->rate, 2) }}</td>
                                    <td class="p-2.5 text-right pr-3">
                                        <form method="POST" action="{{ route('products.price-slabs.destroy', ['product' => $product->id, 'slab' => $slab->id]) }}" onsubmit="return confirm('Delete this price slab?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold text-xs cursor-pointer">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <!-- Add Slab Form -->
            <div class="bg-neutral-50 p-4 rounded-2xl border border-neutral-200/80 space-y-3">
                <h4 class="font-bold text-xs text-neutral-900">Add Pricing Slab</h4>
                <form action="{{ route('products.price-slabs.store', $product->id) }}" method="POST" class="space-y-2.5 text-xs">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-neutral-600 mb-1">Tier / Segment Name *</label>
                        <input type="text" name="tier_name" placeholder="e.g. Wholesale, Export, Container" required class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-lg text-xs bg-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-neutral-600 mb-1">Min Quantity ({{ $product->uom ?? 'KG' }}) *</label>
                        <input type="number" step="0.01" name="min_quantity" placeholder="1000" required class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-lg text-xs font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-neutral-600 mb-1">Special Rate (₹) *</label>
                        <input type="number" step="0.01" name="rate" placeholder="185.00" required class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-lg text-xs font-mono bg-white">
                    </div>
                    <button type="submit" class="w-full py-2 bg-neutral-900 text-[#D7FF53] font-bold rounded-lg hover:bg-black transition-colors cursor-pointer text-xs">
                        + Add Price Slab
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Fields & Technical Specifications Section -->
    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">⚙️</span>
                <div>
                    <h2 class="font-black text-base text-neutral-900 font-display">Custom Fields & Specifications</h2>
                    <p class="text-[11px] text-neutral-500">Tenant-defined dynamic attributes (Mesh size, Moisture %, Crop Year, Quality Grade)</p>
                </div>
            </div>
        </div>

        @if($customFields->isEmpty())
            <div class="py-6 text-center text-xs text-neutral-400 bg-neutral-50 rounded-2xl border border-neutral-100 space-y-2">
                <p>No custom product fields defined yet.</p>
                <form action="{{ route('products.custom-fields.store') }}" method="POST" class="inline-flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="field_name" value="Moisture Content %">
                    <input type="hidden" name="field_type" value="TEXT">
                    <button type="submit" class="px-3 py-1 bg-neutral-900 text-[#D7FF53] font-bold rounded-lg text-xs cursor-pointer">
                        + Create Sample Field (Moisture %)
                    </button>
                </form>
            </div>
        @else
            <form action="{{ route('products.custom-values.update', $product->id) }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php
                        $valuesMap = $product->customValues->keyBy('custom_field_id');
                    @endphp
                    @foreach($customFields as $field)
                        @php
                            $currentVal = $valuesMap[$field->id]->field_value ?? '';
                        @endphp
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">
                                {{ $field->field_name }}
                                @if($field->is_required) <span class="text-rose-500">*</span> @endif
                            </label>
                            @if($field->field_type === 'DROPDOWN' && !empty($field->options))
                                <select name="custom_fields[{{ $field->id }}]" class="w-full px-3 py-1.5 border border-neutral-200 rounded-xl text-xs bg-white">
                                    <option value="">-- Select --</option>
                                    @foreach($field->options as $opt)
                                        <option value="{{ $opt }}" {{ $currentVal == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($field->field_type === 'DATE')
                                <input type="date" name="custom_fields[{{ $field->id }}]" value="{{ $currentVal }}" class="w-full px-3 py-1.5 border border-neutral-200 rounded-xl text-xs font-mono">
                            @elseif($field->field_type === 'NUMBER')
                                <input type="number" step="any" name="custom_fields[{{ $field->id }}]" value="{{ $currentVal }}" class="w-full px-3 py-1.5 border border-neutral-200 rounded-xl text-xs font-mono">
                            @else
                                <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ $currentVal }}" placeholder="Enter {{ $field->field_name }}" class="w-full px-3 py-1.5 border border-neutral-200 rounded-xl text-xs">
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2 rounded-full bg-neutral-900 text-[#D7FF53] hover:bg-black font-extrabold text-xs shadow-xs cursor-pointer transition-all">
                        💾 Save Custom Specifications
                    </button>
                </div>
            </form>
        @endif
    </div>

    <!-- Active Shares List Section -->
    @if($product->shares->count() > 0)
    <div class="bg-white rounded-3xl border border-neutral-200/80 p-5 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
            <div class="flex items-center gap-2">
                <span class="text-xl">🔗</span>
                <div>
                    <h2 class="font-black text-base text-neutral-900 font-display">Active Shared Client Links</h2>
                    <p class="text-[11px] text-neutral-500">Public tokens generated for buyers</p>
                </div>
            </div>
        </div>

        <div class="divide-y divide-neutral-100">
            @foreach($product->shares as $share)
            <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-neutral-900">{{ $share->title }}</span>
                        @if($share->is_expired)
                        <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-bold">Expired</span>
                        @else
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Active</span>
                        @endif
                        @if($share->has_password)
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">🔒 Protected</span>
                        @endif
                    </div>
                    <div class="text-[11px] text-neutral-400 font-mono flex items-center gap-3">
                        <span>Expires: {{ $share->expires_at ? $share->expires_at->format('d M Y H:i') : 'Never' }}</span>
                        <span>•</span>
                        <span>Views: {{ $share->views_count }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('products.documents.shared', $share->share_token) }}" target="_blank"
                        class="px-3 py-1 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 font-bold rounded-lg text-xs">
                        Open Portal ↗
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Inline Preview Modal -->
    <template x-teleport="body">
        <div x-show="previewModalOpen" x-cloak
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4">
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
@endsection
