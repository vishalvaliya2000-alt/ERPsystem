@extends('layouts.app')

@section('title', 'Commercial Shipments & LR Tracking')

@section('content')
<div class="space-y-6 pb-12" x-data="shipmentsPageApp()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)]">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#F5F6F8] text-neutral-600 border border-neutral-200/80 font-mono uppercase tracking-wider">Logistics</span>
                <span class="text-xs text-neutral-300">·</span>
                <span class="text-xs text-neutral-500 font-medium">{{ count($shipments) }} Total Dispatches</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-black text-base shrink-0 shadow-xs border border-neutral-800 font-display">
                    🚚
                </div>
                <div>
                    <h2 class="text-2xl font-black text-neutral-900 tracking-tight font-display">Shipments</h2>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Road transport consignments with Bilty / LR tracking, LR document uploads, and PAID / TO PAY freight accounting.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button
                @click="openNewShipmentModal('SCHEDULED')"
                class="flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold text-neutral-800 bg-[#F5F6F8] hover:bg-neutral-100 border border-neutral-200/80 shadow-2xs transition-all cursor-pointer active:scale-95"
            >
                <span>📅</span>
                <span>Schedule Shipment</span>
            </button>
            <button
                @click="openNewShipmentModal('DISPATCHED')"
                class="flex items-center gap-1.5 px-5 py-2.5 rounded-full text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] shadow-2xs transition-all cursor-pointer active:scale-95"
            >
                <span>+</span>
                <span>Dispatch Material (Create LR)</span>
            </button>
        </div>
    </div>

    <!-- Rule Alert Banner -->
    <div class="bg-[#F3FED4]/60 border border-[#D7FF53] rounded-2xl sm:rounded-3xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-neutral-900">
        <div class="flex items-center gap-3">
            <span class="text-xl">🚚</span>
            <div>
                <span class="font-extrabold block font-display">Freight Terms & Multi-PO Dispatch:</span>
                <span class="text-neutral-700">Select <b>PAID</b> (adds freight to consignment value) or <b>TO PAY</b> (collected by transporter at destination).</span>
            </div>
        </div>
        <span class="text-xs font-bold px-3 py-1 rounded-full bg-white text-[#091315] border border-neutral-200/80 font-mono self-start sm:self-auto shadow-2xs">
            {{ count($availableItems) }} Unshipped Line Items
        </span>
    </div>

    <!-- View Mode Switcher, Status Filters & Quick Search Toolbar -->
    <div class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <button
                type="button"
                @click="filterStatus = 'ALL'"
                :class="filterStatus === 'ALL' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>All Consignments</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'ALL' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-neutral-200 text-neutral-700'">{{ count($shipments) }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'SCHEDULED'"
                :class="filterStatus === 'SCHEDULED' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>📅 Scheduled</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'SCHEDULED' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-amber-100 text-amber-800'">{{ $shipments->where('status', 'SCHEDULED')->count() }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'IN_TRANSIT'"
                :class="filterStatus === 'IN_TRANSIT' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>🚚 In Transit</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'IN_TRANSIT' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-blue-100 text-blue-800'">{{ $shipments->where('status', 'IN_TRANSIT')->count() }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'DELIVERED'"
                :class="filterStatus === 'DELIVERED' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>✓ Delivered</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'DELIVERED' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-emerald-100 text-emerald-800'">{{ $shipments->where('status', 'DELIVERED')->count() }}</span>
            </button>
        </div>

        <!-- Search Bar & View Toggle -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Search Input -->
            <div class="relative flex-1 md:w-64">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400 text-xs">🔍</span>
                <input
                    type="text"
                    x-model="searchQuery"
                    placeholder="Search LR, customer, PO, cargo..."
                    class="w-full pl-9 pr-7 py-2 rounded-full text-xs bg-[#F5F6F8] border border-neutral-200/80 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#091315] transition-all placeholder:text-neutral-400"
                >
                <button
                    x-show="searchQuery"
                    @click="searchQuery = ''"
                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-neutral-400 hover:text-neutral-600 text-xs cursor-pointer"
                >✕</button>
            </div>

            <!-- View Switcher -->
            <div class="flex items-center bg-[#F5F6F8] p-1 rounded-full border border-neutral-200/80 shrink-0">
                <button
                    type="button"
                    @click="setViewMode('cards')"
                    :class="viewMode === 'cards' ? 'bg-[#091315] text-[#D7FF53] shadow-xs font-black' : 'text-neutral-600 hover:text-neutral-900 font-bold'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all cursor-pointer font-display"
                    title="Cards Grid View"
                >
                    <span>🗂️</span>
                    <span class="hidden sm:inline">Cards</span>
                </button>
                <button
                    type="button"
                    @click="setViewMode('table')"
                    :class="viewMode === 'table' ? 'bg-[#091315] text-[#D7FF53] shadow-xs font-black' : 'text-neutral-600 hover:text-neutral-900 font-bold'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all cursor-pointer font-display"
                    title="Streamlined Table View"
                >
                    <span>☰</span>
                    <span class="hidden sm:inline">Table</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 1. MODERN LOGISTICS CARGO CARDS VIEW -->
    <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($shipments as $s)
            @php
                $orders = $s->getDistinctOrders();
                $totalKg = (float) $s->items->sum('quantity');
                $searchHaystack = strtolower("{$s->lr_number} {$s->shipment_number} {$s->customer_names} {$s->transporter} {$s->destination} " . $orders->pluck('order_number')->implode(' ') . ' ' . $s->items->map(fn($it) => $it->salesOrderItem?->product?->product_name)->implode(' '));
            @endphp
            <div
                x-show="matchesFilter('{{ $s->status }}', '{{ addslashes($searchHaystack) }}')"
                class="bg-white rounded-3xl border border-slate-200 shadow-xs hover:shadow-lg transition-all duration-200 flex flex-col justify-between overflow-hidden group hover:border-slate-300"
            >
                <!-- Card Top: Carrier & Status -->
                <div class="p-4 pb-3 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 flex items-center justify-center text-sm shrink-0">
                            {{ $s->status === 'SCHEDULED' ? '📅' : '🚚' }}
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-bold text-xs text-slate-900 truncate" title="{{ $s->transporter ?: 'Awaiting Carrier' }}">
                                {{ $s->transporter ?: 'Awaiting Carrier' }}
                            </h4>
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                                <span>{{ $s->status === 'SCHEDULED' ? 'Planned: ' : '' }}{{ $s->shipment_date->format('d M Y') }}</span>
                                <span>•</span>
                                <span class="font-mono text-slate-500">{{ $s->shipment_number }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Pill -->
                    <span
                        class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shrink-0 border"
                        :class="{
                            'bg-emerald-50 text-emerald-700 border-emerald-200': '{{ $s->status }}' === 'DELIVERED',
                            'bg-blue-50 text-blue-700 border-blue-200 animate-pulse': '{{ $s->status }}' === 'IN_TRANSIT',
                            'bg-amber-100/90 text-amber-900 border-amber-300 font-extrabold': '{{ $s->status }}' === 'SCHEDULED',
                            'bg-slate-100 text-slate-700 border-slate-200': '{{ $s->status }}' !== 'DELIVERED' && '{{ $s->status }}' !== 'IN_TRANSIT' && '{{ $s->status }}' !== 'SCHEDULED'
                        }"
                    >
                        @if($s->status === 'DELIVERED') ✓ Delivered
                        @elseif($s->status === 'IN_TRANSIT') ⚡ In Transit
                        @elseif($s->status === 'SCHEDULED') 📅 Scheduled
                        @else {{ $s->status }}
                        @endif
                    </span>
                </div>

                <!-- Card Middle: LR Hero & Consignment Specs -->
                <div class="p-4 space-y-3.5 flex-1">
                    <!-- LR Hero Banner with 1-Click Track -->
                    <div class="p-3 rounded-2xl bg-white border border-slate-200 flex items-center justify-between shadow-xs">
                        <div class="min-w-0">
                            <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">LR / Bilty Number</span>
                            @if($s->lr_number)
                                <span class="font-mono font-black text-sm text-amber-500 block truncate">
                                    {{ $s->lr_number }}
                                </span>
                            @else
                                <span class="font-bold text-xs text-amber-700 flex items-center gap-1.5 py-0.5">
                                    <span>⏳</span>
                                    <span>Awaiting Truck & LR</span>
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                            @php $lrDoc = $s->lrDocument; @endphp
                            @if($lrDoc)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 text-purple-900 border border-purple-200 text-[10px] font-mono shadow-2xs">
                                    <span class="font-bold">🚚 LR Doc</span>
                                    <a href="{{ route('documents.preview', $lrDoc->id) }}" target="_blank" class="font-bold text-purple-700 hover:underline hover:text-purple-950 ml-0.5">View</a>
                                    <span class="text-purple-300">|</span>
                                    <a href="{{ route('documents.download', $lrDoc->id) }}" class="font-bold text-purple-700 hover:underline hover:text-purple-950">Download</a>
                                </div>
                            @elseif($s->proof_document_url)
                                <a href="/storage/{{ $s->proof_document_url }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-purple-50 text-purple-900 border border-purple-200 text-[10px] font-mono font-bold hover:bg-purple-100 shadow-2xs">
                                    <span>📎 View LR</span>
                                </a>
                            @endif
                            @if($s->tracking_url)
                                <button
                                    type="button"
                                    onclick="openLiveTracking({{ $s->id }}, '{{ $s->lr_number }}', '{{ addslashes($s->transporter) }}')"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[11px] font-black bg-[#091315] hover:bg-neutral-800 text-[#D7FF53] shadow-xs transition-transform active:scale-95 cursor-pointer"
                                    title="Open live radar tracking inside ERP"
                                >
                                    <span>📍</span>
                                    <span>Track</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Customer & Route -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Consignee & Orders</span>
                            @if($orders->isNotEmpty())
                                <div class="flex items-center gap-1">
                                    @foreach($orders as $ord)
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono font-bold text-[10px] border border-slate-200">
                                            {{ $ord->order_number }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="text-sm font-black text-slate-900">
                            {{ $s->customer_names }}
                        </div>
                        <div class="text-xs text-slate-600 flex items-center gap-1.5 pt-0.5">
                            <span class="text-slate-400 font-medium">Route:</span>
                            <span class="font-bold text-slate-700">Factory</span>
                            <span class="text-amber-500 font-bold">➔</span>
                            <span class="font-black text-slate-900">{{ $s->destination ?: 'Consignee Hub' }}</span>
                        </div>
                    </div>

                    <!-- Cargo Items & Loaded Weight Strip -->
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Dispatched Cargo</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-800 text-[10px] font-black">
                                {{ number_format($totalKg) }} KG
                            </span>
                        </div>
                        <div class="space-y-1 max-h-20 overflow-y-auto pr-1">
                            @forelse($s->items as $it)
                                <div class="flex items-center justify-between text-xs text-slate-700">
                                    <span class="truncate pr-2 font-medium">
                                        {{ $it->salesOrderItem->product->product_name ?? 'Commercial Item' }}
                                    </span>
                                    <span class="font-mono font-bold text-slate-900 shrink-0">
                                        {{ number_format($it->quantity) }} kg
                                    </span>
                                </div>
                            @empty
                                <span class="text-xs text-slate-400 italic">Commercial goods</span>
                            @endforelse
                        </div>
                    </div>

                    <!-- Value & Freight Terms Strip -->
                    <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Consignment Value</span>
                            <span class="font-black text-slate-900 text-sm">
                                {{ formatINR($s->total_consignment_value) }}
                            </span>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Freight Terms</span>
                            @if($s->freight_payment_type === 'PAID')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    PAID (₹{{ number_format($s->freight_amount) }})
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                    TO PAY
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card Footer: Quick Actions -->
                <div class="p-3 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        @if($s->status === 'SCHEDULED')
                            <button
                                type="button"
                                @click="openConfirmDispatchModal(@js($s))"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] shadow-2xs transition-all active:scale-95 cursor-pointer"
                            >
                                <span>🚀</span>
                                <span>Confirm Dispatch</span>
                            </button>
                        @elseif($s->lr_url)
                            <a
                                href="{{ $s->lr_url }}"
                                target="_blank"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold text-blue-700 bg-white hover:bg-blue-50 border border-slate-200 shadow-2xs transition-colors"
                            >
                                <span>📄</span>
                                <span>LR Copy</span>
                            </a>
                        @endif

                        @if($s->ewayBill)
                            <a
                                href="{{ route('eway-bills.show', $s->ewayBill->id) }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-extrabold border shadow-2xs transition-all {{ $s->ewayBill->status === 'GENERATED' ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200' }}"
                                title="Form GST EWB-01"
                            >
                                <span>⚡</span>
                                <span>EWB #{{ substr($s->ewayBill->eway_bill_number, -6) }}</span>
                            </a>
                        @else
                            <button
                                type="button"
                                @click="openEwayModal(@js($s))"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-neutral-900 text-[#D7FF53] hover:bg-black border border-neutral-800 shadow-2xs transition-all cursor-pointer"
                                title="Generate Statutory Form GST EWB-01"
                            >
                                <span>⚡</span>
                                <span>E-Way Bill</span>
                            </button>
                        @endif

                        <form action="{{ route('shipments.whatsapp', $s->id) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="p-1.5 rounded-xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 shadow-2xs transition-colors cursor-pointer text-xs font-bold inline-flex items-center"
                                title="Send LR Tracking to Party via WhatsApp"
                            >
                                💬
                            </button>
                        </form>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="openEditModal(@js($s))"
                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 shadow-2xs transition-colors cursor-pointer"
                            title="Edit dispatch details"
                        >
                            ✏️ Edit
                        </button>

                        <form action="{{ route('shipments.destroy', $s->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete shipment {{ $s->lr_number ?: $s->shipment_number }}? This will automatically restore dispatched quantities back to the PO.');">
                            @csrf
                            <button
                                type="submit"
                                class="p-1.5 rounded-xl text-rose-600 bg-white hover:bg-rose-50 border border-rose-200 shadow-2xs transition-colors cursor-pointer text-xs font-bold"
                                title="Delete and restore balances"
                            >
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl mx-auto">🚚</div>
                <h4 class="font-bold text-slate-800">No Consignments Dispatched</h4>
                <p class="text-xs text-slate-400">Click "+ Dispatch Material (Create LR)" to record your first transport consignment.</p>
            </div>
        @endforelse
    </div>

    <!-- 2. STREAMLINED 6-COLUMN LOGISTICS TABLE VIEW -->
    <div x-show="viewMode === 'table'" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 border-b border-slate-200">
                        <th class="p-4 font-bold w-[24%]">Consignment & Carrier</th>
                        <th class="p-4 font-bold w-[20%]">Customer & Route</th>
                        <th class="p-4 font-bold w-[16%]">Dispatch & Order</th>
                        <th class="p-4 font-bold w-[16%]">Cargo & Weight</th>
                        <th class="p-4 font-bold text-right w-[14%]">Value & Freight</th>
                        <th class="p-4 font-bold text-center w-[10%]">Status & Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shipments as $s)
                        @php
                            $orders = $s->getDistinctOrders();
                            $totalKg = (float) $s->items->sum('quantity');
                            $searchHaystack = strtolower("{$s->lr_number} {$s->shipment_number} {$s->customer_names} {$s->transporter} {$s->destination} " . $orders->pluck('order_number')->implode(' ') . ' ' . $s->items->map(fn($it) => $it->salesOrderItem?->product?->product_name)->implode(' '));
                        @endphp
                        <tr
                            x-show="matchesFilter('{{ $s->status }}', '{{ addslashes($searchHaystack) }}')"
                            class="hover:bg-slate-50/70 transition-colors"
                        >
                            <!-- 1. Consignment & Carrier -->
                            <td class="p-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($s->lr_number)
                                            <span class="font-mono font-black text-slate-900 bg-slate-100 px-2 py-0.5 rounded-lg border border-slate-200">
                                                {{ $s->lr_number }}
                                            </span>
                                        @else
                                            <span class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200">
                                                ⏳ Awaiting LR
                                            </span>
                                        @endif
                                        @php $lrDoc = $s->lrDocument; @endphp
                                        @if($lrDoc)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 bg-purple-50 text-purple-900 border border-purple-200 rounded-md">
                                                <a href="{{ route('documents.preview', $lrDoc->id) }}" target="_blank" class="font-bold hover:underline">View</a> |
                                                <a href="{{ route('documents.download', $lrDoc->id) }}" class="font-bold hover:underline">Download</a>
                                            </span>
                                        @elseif($s->proof_document_url)
                                            <a href="/storage/{{ $s->proof_document_url }}" target="_blank" class="text-[10px] text-purple-700 font-mono underline">LR File</a>
                                        @endif
                                        @if($s->tracking_url)
                                            <button
                                                type="button"
                                                onclick="openLiveTracking({{ $s->id }}, '{{ $s->lr_number }}', '{{ addslashes($s->transporter) }}')"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 shadow-2xs cursor-pointer transition-colors"
                                                title="Track live carrier scans"
                                            >
                                                <span>📍</span>
                                                <span>Track</span>
                                            </button>
                                        @endif
                                    </div>
                                    <div class="text-xs font-semibold text-slate-800 truncate" title="{{ $s->transporter }}">
                                        {{ $s->transporter ?: 'Awaiting Transporter' }}
                                    </div>
                                    <div class="font-mono text-[10px] text-slate-400">
                                        {{ $s->shipment_number }}
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Customer & Route -->
                            <td class="p-4">
                                <div class="space-y-1">
                                    <div class="font-black text-slate-900">
                                        {{ $s->customer_names }}
                                    </div>
                                    <div class="text-[11px] text-slate-600 flex items-center gap-1">
                                        <span class="text-slate-400">To:</span>
                                        <span class="font-bold text-slate-800 truncate">{{ $s->destination ?: 'Consignee Hub' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Dispatch & Order -->
                            <td class="p-4">
                                <div class="space-y-1">
                                    <div class="font-semibold text-slate-800">
                                        {{ $s->shipment_date->format('d M Y') }}
                                    </div>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        @if($orders->isNotEmpty())
                                            @foreach($orders as $ord)
                                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono font-bold text-[10px] border border-slate-200">
                                                    {{ $ord->order_number }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-slate-400 font-mono text-[10px]">Direct PO</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- 4. Cargo & Weight -->
                            <td class="p-4">
                                <div class="space-y-1">
                                    <div class="font-black text-slate-900 text-xs">
                                        {{ number_format($totalKg) }} kg
                                    </div>
                                    <div class="text-[11px] text-slate-600 truncate" title="{{ $s->items->map(fn($it) => $it->salesOrderItem?->product?->product_name)->implode(', ') }}">
                                        {{ $s->items->first()?->salesOrderItem?->product?->product_name ?? 'Commercial Goods' }}
                                        @if($s->items->count() > 1)
                                            <span class="text-slate-400 text-[10px] font-medium">+{{ $s->items->count() - 1 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- 5. Value & Freight -->
                            <td class="p-4 text-right">
                                <div class="space-y-1">
                                    <div class="font-black text-slate-900">
                                        {{ formatINR($s->total_consignment_value) }}
                                    </div>
                                    <div>
                                        @if($s->freight_payment_type === 'PAID')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                PAID (₹{{ number_format($s->freight_amount) }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                                TO PAY
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- 6. Status & Actions -->
                            <td class="p-4 text-center">
                                <div class="space-y-2 flex flex-col items-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border-emerald-200': '{{ $s->status }}' === 'DELIVERED',
                                            'bg-blue-50 text-blue-700 border-blue-200 animate-pulse': '{{ $s->status }}' === 'IN_TRANSIT',
                                            'bg-amber-100/90 text-amber-900 border-amber-300 font-extrabold': '{{ $s->status }}' === 'SCHEDULED',
                                            'bg-slate-100 text-slate-700 border-slate-200': '{{ $s->status }}' !== 'DELIVERED' && '{{ $s->status }}' !== 'IN_TRANSIT' && '{{ $s->status }}' !== 'SCHEDULED'
                                        }"
                                    >
                                        @if($s->status === 'DELIVERED') ✓ Delivered
                                        @elseif($s->status === 'IN_TRANSIT') ⚡ In Transit
                                        @elseif($s->status === 'SCHEDULED') 📅 Scheduled
                                        @else {{ $s->status }}
                                        @endif
                                    </span>

                                    <div class="flex items-center justify-center gap-1 flex-wrap">
                                        @if($s->status === 'SCHEDULED')
                                            <button
                                                type="button"
                                                @click="openConfirmDispatchModal(@js($s))"
                                                class="px-2 py-1 rounded-lg text-[10px] font-black bg-[#D7FF53] text-[#091315] hover:bg-[#c8f043] border border-[#c8f043] shadow-2xs cursor-pointer flex items-center gap-1 active:scale-95 transition-all"
                                                title="Confirm vehicle arrival and enter LR"
                                            >
                                                <span>🚀</span>
                                                <span>Dispatch</span>
                                            </button>
                                        @endif

                                        @if($s->lr_url)
                                            <a
                                                href="{{ $s->lr_url }}"
                                                target="_blank"
                                                class="p-1.5 rounded-lg text-blue-700 hover:bg-blue-50 border border-slate-200 text-xs font-bold"
                                                title="View LR Document"
                                            >
                                                📄
                                            </a>
                                        @endif

                                        @if($s->ewayBill)
                                            <a
                                                href="{{ route('eway-bills.show', $s->ewayBill->id) }}"
                                                class="px-2 py-1 rounded-lg text-[10px] font-bold border transition-colors {{ $s->ewayBill->status === 'GENERATED' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}"
                                                title="View Form GST EWB-01"
                                            >
                                                ⚡ EWB
                                            </a>
                                        @else
                                            <button
                                                type="button"
                                                @click="openEwayModal(@js($s))"
                                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-neutral-900 text-[#D7FF53] hover:bg-black border border-neutral-800 cursor-pointer shadow-2xs"
                                                title="Generate E-Way Bill"
                                            >
                                                ⚡ EWB
                                            </button>
                                        @endif

                                        <form action="{{ route('shipments.whatsapp', $s->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="p-1.5 rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-xs font-bold cursor-pointer"
                                                title="WhatsApp LR Tracking"
                                            >
                                                💬
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            @click="openEditModal(@js($s))"
                                            class="p-1.5 rounded-lg text-slate-700 hover:bg-slate-100 border border-slate-200 text-xs font-bold cursor-pointer"
                                            title="Edit Shipment"
                                        >
                                            ✏️
                                        </button>

                                        <form action="{{ route('shipments.destroy', $s->id) }}" method="POST" onsubmit="return confirm('Delete shipment {{ $s->lr_number ?: $s->shipment_number }} and restore PO balances?');">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 border border-rose-200 text-xs font-bold cursor-pointer"
                                                title="Delete"
                                            >
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400 italic">
                                No commercial shipments dispatched yet. Click "+ Dispatch Material (Create LR)" to create your first dispatch.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Shipment Modal (Multi-PO, Freight Type & LR Upload Support) -->
    <template x-teleport="body">
        <div x-show="isNewShipmentOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isNewShipmentOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-3xl overflow-hidden max-h-[92vh] flex flex-col text-xs">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold" x-text="shipmentMode === 'SCHEDULED' ? '📅' : '🚚'">
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display" x-text="shipmentMode === 'SCHEDULED' ? 'Schedule Future Shipment (Planning)' : 'Dispatch Material & Create LR'"></h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="shipmentMode === 'SCHEDULED' ? 'Reserve PO balance for agreed dispatch date • Assign LR on gate-out' : 'Combine items across POs • Choose PAID or TO PAY freight'"></p>
                        </div>
                    </div>
                    <button @click="isNewShipmentOpen = false" class="text-neutral-400 hover:text-white cursor-pointer">✕</button>
                </div>

            @if(empty($availableItems))
                <div class="p-8 text-center space-y-3 text-xs">
                    <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xl mx-auto font-black">
                        ✓
                    </div>
                    <h4 class="font-black text-sm text-slate-900">No Open POs with Pending Balance</h4>
                    <p class="text-slate-500">
                        All Purchase Orders have been 100% fully delivered. To dispatch new material, create a new Sales Order first.
                    </p>
                    <div class="pt-2">
                        <a href="{{ route('orders.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-slate-900 inline-block">
                            + Go to Sales Orders
                        </a>
                    </div>
                </div>
            @else
                <form action="{{ route('shipments.store') }}" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1" x-data="{ selectedLrFile: null }">
                    @csrf
                    <input type="hidden" name="status" :value="shipmentMode">

                    <!-- Workflow Mode Switcher -->
                    <div class="p-1 bg-slate-100 rounded-2xl flex items-center gap-1 border border-slate-200">
                        <button
                            type="button"
                            @click="shipmentMode = 'DISPATCHED'"
                            :class="shipmentMode === 'DISPATCHED' ? 'bg-white text-slate-900 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="flex-1 py-2 px-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>🚚</span>
                            <span>Dispatch Now (With LR)</span>
                        </button>
                        <button
                            type="button"
                            @click="shipmentMode = 'SCHEDULED'"
                            :class="shipmentMode === 'SCHEDULED' ? 'bg-[#091315] text-[#D7FF53] shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="flex-1 py-2 px-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>📅</span>
                            <span>Schedule Future (Planning)</span>
                        </button>
                    </div>

                    <!-- Transporter Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Transporter <span x-show="shipmentMode === 'DISPATCHED'">*</span> <span x-show="shipmentMode === 'SCHEDULED'" class="text-slate-400 font-normal">(Optional - TBD)</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="transporter" list="registeredTransportersList" :placeholder="shipmentMode === 'SCHEDULED' ? 'TBD or Carrier Name' : 'e.g. Mahalakshmi Transport...'" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold text-slate-900" :required="shipmentMode === 'DISPATCHED'">
                                <datalist id="registeredTransportersList">
                                    @foreach($transporters as $t)
                                        <option value="{{ $t->transporter_name }}">{{ $t->transporter_name }} {{ $t->transporter_id_gst ? "({$t->transporter_id_gst})" : '' }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                LR / Bilty Number <span x-show="shipmentMode === 'DISPATCHED'">*</span> <span x-show="shipmentMode === 'SCHEDULED'" class="text-slate-400 font-normal">(Optional - assign on dispatch)</span>
                            </label>
                            <input type="text" name="lr_number" :placeholder="shipmentMode === 'SCHEDULED' ? 'Leave blank or enter if ready' : 'e.g. 2632801254'" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold" :required="shipmentMode === 'DISPATCHED'">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                <span x-text="shipmentMode === 'SCHEDULED' ? 'Planned Dispatch Date *' : 'Dispatch Date *'"></span>
                            </label>
                            <input type="date" name="shipment_date" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Truck / Vehicle No.</label>
                            <input type="text" name="vehicle_number" placeholder="GJ-04-XX-1234" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Destination</label>
                            <input type="text" name="destination" placeholder="Customer Godown / City" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                    </div>

                    <!-- Freight Payment Type (TO PAY vs PAID) -->
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="block font-black text-slate-900">Freight Payment Mode *</label>
                            <span class="text-[11px] text-slate-500 font-semibold">Who pays the transporter freight?</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border cursor-pointer transition-all" :class="freightPaymentType === 'TO_PAY' ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-400/20' : 'bg-white border-slate-200'">
                                <input type="radio" name="freight_payment_type" value="TO_PAY" x-model="freightPaymentType" class="text-amber-600">
                                <div>
                                    <span class="font-bold text-slate-900 block">🚚 TO PAY</span>
                                    <span class="text-[10px] text-slate-500">Freight collected from customer at destination</span>
                                </div>
                            </label>

                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border cursor-pointer transition-all" :class="freightPaymentType === 'PAID' ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-400/20' : 'bg-white border-slate-200'">
                                <input type="radio" name="freight_payment_type" value="PAID" x-model="freightPaymentType" class="text-emerald-600">
                                <div>
                                    <span class="font-bold text-slate-900 block">🟢 PAID (Prepaid)</span>
                                    <span class="text-[10px] text-slate-500">Freight paid by us & added to consignment value</span>
                                </div>
                            </label>
                        </div>

                        <!-- If PAID: Enter Freight Amount -->
                        <div x-show="freightPaymentType === 'PAID'" class="pt-2 border-t border-slate-200/80">
                            <label class="block font-bold text-emerald-950 mb-1">Prepaid Freight Amount (₹) *</label>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                name="freight_amount"
                                x-model="freightAmount"
                                placeholder="e.g. 15000"
                                class="w-full px-3 py-2 border border-emerald-300 rounded-lg font-bold bg-white text-emerald-950"
                                :required="freightPaymentType === 'PAID'"
                            >
                            <p class="text-[10px] text-emerald-700 mt-1">This amount will be included in the total consignment value.</p>
                        </div>
                    </div>

                    <!-- Transport / LR Document Upload -->
                    <div class="p-3.5 bg-[#F5F6F8] rounded-2xl border border-neutral-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-neutral-800 text-xs">Transport / LR Document</label>
                            <span class="text-[10px] font-mono text-neutral-500">PDF, JPG, PNG, WEBP (Max 25 MB)</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="px-4 py-2 bg-white hover:bg-neutral-50 text-neutral-800 border border-neutral-300 rounded-xl font-bold text-xs cursor-pointer shadow-2xs inline-flex items-center gap-1.5 transition-all">
                                <span>📎</span>
                                <span>Choose File</span>
                                <input 
                                    type="file" 
                                    name="lr_document" 
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,image/*" 
                                    class="hidden" 
                                    @change="selectedLrFile = $event.target.files[0]"
                                >
                            </label>
                            <template x-if="selectedLrFile">
                                <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-neutral-200 text-xs shadow-2xs">
                                    <span class="font-mono text-neutral-700 font-bold truncate max-w-[220px]" x-text="selectedLrFile.name"></span>
                                    <span class="text-[10px] text-neutral-400 font-mono" x-text="'(' + (selectedLrFile.size / 1024 / 1024).toFixed(2) + ' MB)'"></span>
                                    <button type="button" @click="selectedLrFile = null; $el.closest('.p-3.5').querySelector('input[type=file]').value = ''" class="text-neutral-400 hover:text-rose-600 font-bold ml-1 cursor-pointer" title="Remove selected file">✕</button>
                                </div>
                            </template>
                            <template x-if="!selectedLrFile">
                                <span class="text-[11px] text-neutral-400 italic">No file selected (optional)</span>
                            </template>
                        </div>
                    </div>

                    <!-- Multi-Item / Multi-PO Dispatch Section -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-black text-sm text-slate-900 block">Dispatch Line Items (From Single or Multiple POs)</span>
                                <span class="text-[11px] text-slate-500">Pick any line item from any open PO and enter dispatch quantity</span>
                            </div>
                            <button
                                type="button"
                                @click="addShipmentItemRow()"
                                class="px-3 py-1 rounded-lg text-xs font-bold text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 flex items-center gap-1"
                            >
                                + Add Another PO Item
                            </button>
                        </div>

                        <div class="space-y-2.5">
                            <template x-for="(item, idx) in newShipmentItems" :key="idx">
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 relative">
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                                        <div class="sm:col-span-8">
                                            <label class="block font-bold text-slate-700 mb-1" x-text="'Item #' + (idx + 1) + ' — Select PO & Product Cut'"></label>
                                            <select
                                                :name="'items[' + idx + '][sales_order_item_id]'"
                                                x-model="item.sales_order_item_id"
                                                @change="onItemSelect(item)"
                                                class="w-full px-2.5 py-2 border border-slate-300 rounded-lg font-medium"
                                                required
                                            >
                                                @foreach($availableItems as $av)
                                                    <option value="{{ $av['id'] }}" data-balance="{{ $av['balance_qty'] }}" data-rate="{{ $av['rate'] }}">
                                                        {{ $av['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block font-bold text-slate-700">Dispatch Qty (KG) *</label>
                                                <span class="text-[10px] text-amber-700 font-bold" x-text="'Max: ' + (item.maxBalance || 0)"></span>
                                            </div>
                                            <input
                                                type="number"
                                                step="0.5"
                                                :name="'items[' + idx + '][quantity]'"
                                                x-model="item.quantity"
                                                :max="item.maxBalance"
                                                placeholder="e.g. 5000"
                                                class="w-full px-2.5 py-2 border border-slate-300 rounded-lg font-bold"
                                                required
                                            >
                                        </div>

                                        <div class="sm:col-span-1 flex justify-end sm:justify-center pb-2">
                                            <button
                                                type="button"
                                                x-show="newShipmentItems.length > 1"
                                                @click="removeShipmentItemRow(idx)"
                                                class="w-7 h-7 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 flex items-center justify-center font-black text-xs"
                                                title="Remove Item"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Item value calculation -->
                                    <div class="flex justify-between items-center pt-1.5 text-[11px] text-slate-500 font-semibold">
                                        <span>Rate: ₹<span x-text="item.rate || 0"></span>/KG</span>
                                        <span>Line Value: <b class="text-slate-900" x-text="'₹' + (((parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0))).toLocaleString('en-IN')"></b></span>
                                    </div>

                                    <!-- Warning if exceeded -->
                                    <div x-show="parseFloat(item.quantity) > parseFloat(item.maxBalance)" class="mt-2 p-2 bg-rose-50 border border-rose-200 rounded text-[11px] text-rose-700 font-bold">
                                        ⚠️ Quantity cannot exceed available balance of <span x-text="item.maxBalance"></span> KG.
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Live Total Breakdown Card (Material + Freight = Total Consignment Value) -->
                    <div class="p-3.5 bg-slate-900 text-white rounded-xl space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-slate-400 font-medium">
                            <span>Total Consignment Weight:</span>
                            <span class="font-bold text-white" x-text="calculateTotalWeight().toLocaleString('en-IN') + ' KG'"></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400 font-medium">
                            <span>Material Value (Products):</span>
                            <span class="font-bold text-white" x-text="'₹' + calculateMaterialValue().toLocaleString('en-IN')"></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400 font-medium">
                            <span>Freight (<span x-text="freightPaymentType === 'PAID' ? 'PAID' : 'TO PAY'"></span>):</span>
                            <span class="font-bold" :class="freightPaymentType === 'PAID' ? 'text-emerald-400' : 'text-amber-400'" x-text="freightPaymentType === 'PAID' ? '+ ₹' + (parseFloat(freightAmount) || 0).toLocaleString('en-IN') : 'TO PAY (₹0 added)'"></span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800 text-white font-black">
                            <span class="text-xs uppercase tracking-wider text-amber-300">Total Consignment Value:</span>
                            <span class="text-base text-amber-400" x-text="'₹' + calculateTotalConsignmentValue().toLocaleString('en-IN')"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Notes / Driver Details</label>
                        <textarea name="notes" rows="2" placeholder="Driver contact, godown door delivery instructions..." class="w-full p-2.5 border border-slate-300 rounded-lg"></textarea>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                        <button type="button" @click="isNewShipmentOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                        <button
                            type="submit"
                            :disabled="hasInvalidQuantities()"
                            class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] disabled:opacity-50 disabled:cursor-not-allowed rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span x-text="shipmentMode === 'SCHEDULED' ? '📅 Save Scheduled Shipment Plan' : '🚚 Dispatch & Create LR'"></span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    </template>

    <!-- Confirm Dispatch Modal (Transition SCHEDULED to DISPATCHED) -->
    <template x-teleport="body">
        <div x-show="isConfirmDispatchOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isConfirmDispatchOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-xl overflow-hidden flex flex-col max-h-[92vh] text-xs">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg font-bold">
                            🚀
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Confirm Dispatch & Assign LR</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="'Shipment: ' + (confirmShipment.shipment_number || '')"></p>
                        </div>
                    </div>
                    <button @click="isConfirmDispatchOpen = false" class="text-neutral-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <form :action="'/shipments/' + confirmShipment.id + '/update'" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1" x-data="{ selectedConfirmDoc: null }">
                    @csrf
                    <input type="hidden" name="status" value="DISPATCHED">

                    <div class="p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 space-y-1">
                        <div class="font-bold flex items-center gap-1.5 text-xs">
                            <span>🚛</span>
                            <span>Vehicle Placement & Factory Gate-Out</span>
                        </div>
                        <p class="text-[11px] text-amber-800">
                            Confirm carrier LR / Bilty number and vehicle details. This marks the shipment as officially <b>DISPATCHED</b> and initiates delivery follow-up tracking.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Transporter / Carrier *</label>
                            <div class="relative">
                                <input type="text" name="transporter" list="registeredTransportersList" x-model="confirmTransporter" placeholder="e.g. Mahalakshmi Transport" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold text-slate-900" required>
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">LR / Bilty Number *</label>
                            <input type="text" name="lr_number" x-model="confirmLrNumber" placeholder="e.g. 2632801254" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Actual Dispatch Date *</label>
                            <input type="date" name="shipment_date" x-model="confirmDispatchDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-medium" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Truck / Vehicle No.</label>
                            <input type="text" name="vehicle_number" x-model="confirmVehicle" placeholder="GJ-04-XX-1234" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Destination</label>
                            <input type="text" name="destination" x-model="confirmDestination" placeholder="Consignee Godown" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                    </div>

                    <!-- Freight Mode in Confirm -->
                    <div class="p-3 bg-[#F5F6F8] rounded-2xl border border-neutral-200/80 space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Freight Mode *</label>
                                <select name="freight_payment_type" x-model="confirmFreightType" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                                    <option value="TO_PAY">TO PAY (Destination)</option>
                                    <option value="PAID">PAID (Prepaid)</option>
                                </select>
                            </div>
                            <div x-show="confirmFreightType === 'PAID'">
                                <label class="block font-bold text-neutral-700 mb-1">Freight Amount (₹)</label>
                                <input type="number" step="1" name="freight_amount" x-model="confirmFreightAmount" placeholder="e.g. 12000" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>
                    </div>

                    <!-- LR Document Upload -->
                    <div class="p-3 bg-[#F5F6F8] rounded-2xl border border-neutral-200/80 space-y-2">
                        <label class="block font-bold text-neutral-800 text-xs">Attach LR / Bilty Document Copy</label>
                        <div class="flex items-center gap-3">
                            <label class="px-4 py-2 bg-white hover:bg-neutral-50 text-neutral-800 border border-neutral-300 rounded-xl font-bold text-xs cursor-pointer shadow-2xs inline-flex items-center gap-1.5 transition-all">
                                <span>📎</span>
                                <span>Choose LR Copy</span>
                                <input 
                                    type="file" 
                                    name="lr_document" 
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,image/*" 
                                    class="hidden" 
                                    @change="selectedConfirmDoc = $event.target.files[0]"
                                >
                            </label>
                            <template x-if="selectedConfirmDoc">
                                <span class="font-mono text-neutral-700 font-bold truncate max-w-[200px]" x-text="selectedConfirmDoc.name"></span>
                            </template>
                            <template x-if="!selectedConfirmDoc">
                                <span class="text-[11px] text-neutral-400 italic">Optional (Can upload later)</span>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Driver Details / Notes</label>
                        <textarea name="notes" x-model="confirmNotes" rows="2" placeholder="Driver contact, godown door delivery instructions..." class="w-full p-2.5 border border-slate-300 rounded-lg"></textarea>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                        <button type="button" @click="isConfirmDispatchOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                        <button
                            type="submit"
                            class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span>🚀</span>
                            <span>Confirm Dispatch Now</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Edit Shipment Modal -->
    <template x-teleport="body">
        <div x-show="isEditModalOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isEditModalOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-lg overflow-hidden flex flex-col max-h-[92vh]">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            🚚
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Shipment & LR Copy</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="'LR: ' + (editShipment.lr_number || '')"></p>
                        </div>
                    </div>
                    <button @click="isEditModalOpen = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors">✕</button>
                </div>

            <form :action="'/shipments/' + editShipment.id + '/update'" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5 space-y-3 text-xs overflow-y-auto flex-1">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Transporter *</label>
                        <input type="text" name="transporter" x-model="editShipment.transporter" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">LR / Bilty Number *</label>
                        <input type="text" name="lr_number" x-model="editShipment.lr_number" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                    </div>
                </div>

                <!-- Freight Mode in Edit -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-[#F5F6F8] rounded-2xl border border-neutral-200/80">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Freight Mode *</label>
                        <select name="freight_payment_type" x-model="editShipment.freight_payment_type" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            <option value="TO_PAY">TO PAY (Destination)</option>
                            <option value="PAID">PAID (Prepaid)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Freight Amount (₹)</label>
                        <input type="number" step="1" name="freight_amount" x-model="editShipment.freight_amount" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Dispatch Date *</label>
                        <input type="date" name="shipment_date" x-model="editShipmentDate" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Status</label>
                        <select name="status" x-model="editShipment.status" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            <option value="SCHEDULED">Scheduled</option>
                            <option value="DISPATCHED">Dispatched</option>
                            <option value="IN_TRANSIT">In Transit</option>
                            <option value="DELIVERED">Delivered</option>
                            <option value="CONFIRMED">Confirmed</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Truck / Vehicle No.</label>
                        <input type="text" name="vehicle_number" x-model="editShipment.vehicle_number" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Destination</label>
                        <input type="text" name="destination" x-model="editShipment.destination" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                    </div>
                </div>

                <!-- Update LR File -->
                <div class="p-3 bg-[#F5F6F8] rounded-2xl border border-neutral-200/80">
                    <label class="block font-bold text-neutral-800 mb-1">📎 Replace / Upload LR Document</label>
                    <input
                        type="file"
                        name="lr_document"
                        accept=".pdf,image/*"
                        class="w-full text-xs text-neutral-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#091315] file:text-white hover:file:bg-neutral-800 cursor-pointer"
                    >
                    <div x-show="editShipment.proof_document_url" class="pt-1.5">
                        <a :href="'/storage/' + editShipment.proof_document_url" target="_blank" class="text-neutral-900 font-bold text-[11px] underline">
                            Current Uploaded LR Copy →
                        </a>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-neutral-700 mb-1">Notes</label>
                    <textarea name="notes" x-model="editShipment.notes" rows="2" class="w-full p-2.5 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"></textarea>
                </div>

                <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                    <button type="button" @click="isEditModalOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    </template>

    <!-- 4. GENERATE STATUTORY E-WAY BILL MODAL -->
    <template x-if="isEwayModalOpen">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-2xl border border-neutral-200">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-neutral-900 text-[#D7FF53] flex items-center justify-center font-black text-lg">
                        ⚡
                    </div>
                    <div>
                        <h3 class="font-black text-neutral-900 text-base font-display">Generate E-Way Bill</h3>
                        <p class="text-[11px] text-neutral-500 font-mono">Form GST EWB-01 · Rule 138 Transit Pass</p>
                    </div>
                </div>
                <button type="button" @click="isEwayModalOpen = false" class="text-neutral-400 hover:text-neutral-600 text-lg cursor-pointer">✕</button>
            </div>

            <form :action="'/shipments/' + ewayShipment.id + '/eway'" method="POST" class="space-y-4 text-xs">
                @csrf
                <div class="p-3 bg-neutral-50 rounded-2xl border border-neutral-200 space-y-1">
                    <div class="flex justify-between text-neutral-700">
                        <span class="font-bold">Shipment Consignment:</span>
                        <span class="font-mono font-black text-neutral-900" x-text="ewayShipment.lr_number || ewayShipment.shipment_number"></span>
                    </div>
                    <div class="flex justify-between text-neutral-700">
                        <span class="font-bold">Consignee Destination:</span>
                        <span class="font-bold text-neutral-900" x-text="ewayShipment.destination || 'Consignee Hub'"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Transit Distance (KM) *</label>
                        <input
                            type="number"
                            name="distance_km"
                            x-model="ewayDistance"
                            min="1"
                            max="4000"
                            required
                            class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] font-semibold"
                        >
                        <span class="text-[10px] text-neutral-400 block mt-0.5">1 day validity per 200 km</span>
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Destination PIN Code</label>
                        <input
                            type="text"
                            name="destination_pincode"
                            x-model="ewayPincode"
                            maxlength="6"
                            placeholder="e.g. 380015"
                            class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] font-mono"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Vehicle / Truck No. *</label>
                        <input
                            type="text"
                            name="vehicle_number"
                            x-model="ewayVehicle"
                            required
                            placeholder="e.g. GJ01AB1234"
                            class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] font-mono uppercase"
                        >
                    </div>
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Transporter / Carrier</label>
                        <input
                            type="text"
                            name="transporter"
                            x-model="ewayTransporter"
                            placeholder="Carrier Name"
                            class="w-full px-3 py-2 border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315]"
                        >
                    </div>
                </div>

                <div class="p-3 bg-[#F3FED4]/50 border border-[#D7FF53] rounded-2xl text-[11px] text-neutral-800">
                    <span class="font-extrabold block">Official Statutory Document:</span>
                    <span>Generates a unique 12-digit government E-way bill number with encrypted verification QR code and Part-A / Part-B conveyance slip.</span>
                </div>

                <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                    <button type="button" @click="isEwayModalOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer flex items-center gap-1.5">
                        <span>⚡</span>
                        <span>Generate E-Way Bill</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>
</div>

<script>
    function shipmentsPageApp() {
        const availableList = @js($availableItems);
        return {
            viewMode: localStorage.getItem('shipments_view_mode') || 'cards',
            filterStatus: 'ALL',
            searchQuery: '',
            setViewMode(mode) {
                this.viewMode = mode;
                try {
                    localStorage.setItem('shipments_view_mode', mode);
                } catch(e) {}
            },
            matchesFilter(status, searchHaystack) {
                if (this.filterStatus !== 'ALL' && status !== this.filterStatus) {
                    return false;
                }
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase().trim();
                    return (searchHaystack || '').toLowerCase().includes(q);
                }
                return true;
            },
            shipmentMode: 'DISPATCHED',
            isNewShipmentOpen: false,
            isEditModalOpen: false,
            isConfirmDispatchOpen: false,
            confirmShipment: {},
            confirmDispatchDate: '{{ date("Y-m-d") }}',
            confirmTransporter: '',
            confirmLrNumber: '',
            confirmVehicle: '',
            confirmDestination: '',
            confirmFreightType: 'TO_PAY',
            confirmFreightAmount: '',
            confirmNotes: '',
            editShipment: {},
            editShipmentDate: '',
            freightPaymentType: 'TO_PAY',
            freightAmount: '',
            newShipmentItems: [],
            availableList: availableList,
            openNewShipmentModal(mode = 'DISPATCHED') {
                this.shipmentMode = mode;
                this.freightPaymentType = 'TO_PAY';
                this.freightAmount = '';
                if (this.availableList.length > 0) {
                    const first = this.availableList[0];
                    this.newShipmentItems = [
                        { sales_order_item_id: first.id, quantity: Math.min(5000, first.balance_qty), maxBalance: first.balance_qty, rate: first.rate }
                    ];
                } else {
                    this.newShipmentItems = [];
                }
                this.isNewShipmentOpen = true;
            },
            openConfirmDispatchModal(s) {
                this.confirmShipment = s;
                this.confirmDispatchDate = formatInputDate(s.shipment_date_formatted || s.shipment_date) || '{{ date("Y-m-d") }}';
                this.confirmTransporter = (s.transporter && s.transporter !== 'TBD (To Be Decided)' && s.transporter !== 'TBD') ? s.transporter : '';
                this.confirmLrNumber = s.lr_number || '';
                this.confirmVehicle = s.vehicle_number || '';
                this.confirmDestination = s.destination || '';
                this.confirmFreightType = s.freight_payment_type || 'TO_PAY';
                this.confirmFreightAmount = s.freight_amount || '';
                this.confirmNotes = s.notes || '';
                this.isConfirmDispatchOpen = true;
            },
            addShipmentItemRow() {
                if (this.availableList.length > 0) {
                    const first = this.availableList[0];
                    this.newShipmentItems.push({
                        sales_order_item_id: first.id,
                        quantity: Math.min(1000, first.balance_qty),
                        maxBalance: first.balance_qty,
                        rate: first.rate
                    });
                }
            },
            removeShipmentItemRow(idx) {
                if (this.newShipmentItems.length > 1) {
                    this.newShipmentItems.splice(idx, 1);
                }
            },
            onItemSelect(item) {
                const found = this.availableList.find(av => av.id == item.sales_order_item_id);
                if (found) {
                    item.maxBalance = found.balance_qty;
                    item.rate = found.rate;
                    if (parseFloat(item.quantity) > found.balance_qty) {
                        item.quantity = found.balance_qty;
                    }
                }
            },
            calculateTotalWeight() {
                return this.newShipmentItems.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0), 0);
            },
            calculateMaterialValue() {
                return this.newShipmentItems.reduce((sum, it) => sum + ((parseFloat(it.quantity) || 0) * (parseFloat(it.rate) || 0)), 0);
            },
            calculateTotalConsignmentValue() {
                const matVal = this.calculateMaterialValue();
                const frt = (this.freightPaymentType === 'PAID') ? (parseFloat(this.freightAmount) || 0) : 0;
                return matVal + frt;
            },
            hasInvalidQuantities() {
                if (this.newShipmentItems.length === 0) return true;
                if (this.freightPaymentType === 'PAID' && (!this.freightAmount || parseFloat(this.freightAmount) < 0)) return true;
                return this.newShipmentItems.some(it => !it.quantity || parseFloat(it.quantity) <= 0 || parseFloat(it.quantity) > parseFloat(it.maxBalance));
            },
            openEditModal(shipment) {
                this.editShipment = shipment;
                this.editShipmentDate = formatInputDate(shipment.shipment_date_formatted || shipment.shipment_date) || '{{ date("Y-m-d") }}';
                this.isEditModalOpen = true;
            },
            isEwayModalOpen: false,
            ewayShipment: {},
            ewayDistance: 120,
            ewayPincode: '',
            ewayVehicle: '',
            ewayTransporter: '',
            openEwayModal(shipment) {
                this.ewayShipment = shipment;
                this.ewayVehicle = shipment.vehicle_number || '';
                this.ewayTransporter = (shipment.transporter && shipment.transporter !== 'TBD (To Be Decided)' && shipment.transporter !== 'TBD') ? shipment.transporter : '';
                this.ewayDistance = 120;
                this.ewayPincode = '';
                this.isEwayModalOpen = true;
            }
        }
    }
</script>
@endsection
