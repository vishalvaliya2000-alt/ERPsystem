@extends('layouts.app')

@section('title', 'GST Invoices & Payment Ledger')

@section('content')
<div class="space-y-6 pb-12" x-data="invoicesPageApp()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)]">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#F5F6F8] text-neutral-600 border border-neutral-200/80 font-mono uppercase tracking-wider">Accounting</span>
                <span class="text-xs text-neutral-300">·</span>
                <span class="text-xs text-neutral-500 font-medium">{{ count($invoices) }} Invoices Issued</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-black text-base shrink-0 shadow-xs border border-neutral-800 font-display">
                    🧾
                </div>
                <div>
                    <h2 class="text-2xl font-black text-neutral-900 tracking-tight font-display">Invoices & AR Ledger</h2>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Tax invoices linked to POs and Shipment LRs, item-level shipment splitting across multiple POs, and freight reconciliation.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button
                @click="openNewInvoiceModal()"
                class="flex items-center gap-1.5 px-5 py-2.5 rounded-full text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] shadow-2xs transition-all cursor-pointer active:scale-95"
            >
                <span>+</span>
                <span>Generate Invoice</span>
            </button>
            <button
                @click="isCombineModalOpen = true"
                class="flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold text-neutral-900 bg-amber-100 hover:bg-amber-200 border border-amber-300 shadow-2xs transition-all cursor-pointer active:scale-95"
            >
                <span>📦+</span>
                <span>Combine Orders</span>
            </button>
            <button
                @click="isNewReceiptOpen = true"
                class="flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold text-white bg-[#091315] hover:bg-black shadow-2xs transition-all cursor-pointer active:scale-95"
            >
                <span>💳</span>
                <span>Payment Receipt</span>
            </button>
            <a href="{{ route('excel.export') }}" class="px-4 py-2.5 rounded-full text-xs font-bold text-neutral-800 bg-[#F5F6F8] hover:bg-neutral-100 border border-neutral-200/80 shadow-2xs transition-all flex items-center gap-1.5">
                <span>📊</span>
                <span>Export Excel</span>
            </a>
        </div>
    </div>

    <!-- Rule Alert Banner -->
    <div class="bg-[#F3FED4]/60 border border-[#D7FF53] rounded-2xl sm:rounded-3xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-neutral-900">
        <div class="flex items-center gap-3">
            <span class="text-xl">🛡️</span>
            <div>
                <span class="font-extrabold block font-display">Multi-PO Shipment Invoicing:</span>
                <span class="text-neutral-700">You can split 1 Shipment LR across multiple invoices by selecting specific PO products for each invoice.</span>
            </div>
        </div>
        <span class="text-xs font-bold px-3 py-1 rounded-full bg-white text-[#091315] border border-neutral-200/80 font-mono self-start sm:self-auto shadow-2xs">
            {{ count($shipmentOptions) }} Invoicable Shipments
        </span>
    </div>

    <!-- Financial KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)]">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block font-mono">Total Invoiced</span>
            <p class="text-2xl font-black text-neutral-900 mt-1 font-display">{{ formatINR($totalBilled) }}</p>
            <p class="text-[11px] text-neutral-500 font-bold mt-1 font-mono">{{ count($invoices) }} Tax Invoices</p>
        </div>

        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)]">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block font-mono">Total Collections</span>
            <p class="text-2xl font-black text-emerald-600 mt-1 font-display">{{ formatINR($totalReceived) }}</p>
            <p class="text-[11px] text-emerald-700 font-bold mt-1 font-mono">{{ count($receipts) }} Settlements</p>
        </div>

        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)]">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block font-mono">Balance Outstanding</span>
            <p class="text-2xl font-black text-rose-600 mt-1 font-display">{{ formatINR($totalOutstanding) }}</p>
            <p class="text-[11px] text-rose-600 font-bold mt-1 font-mono">{{ $pendingCount }} Invoices Awaiting Payment</p>
        </div>
    </div>

    <!-- View Switcher, Status Filters & Live Search Toolbar -->
    <div class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <button
                type="button"
                @click="filterStatus = 'ALL'"
                :class="filterStatus === 'ALL' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>All Invoices</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'ALL' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-neutral-200 text-neutral-700'">{{ count($invoices) }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'UNPAID'"
                :class="filterStatus === 'UNPAID' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>⏳ Awaiting Payment</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'UNPAID' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-amber-100 text-amber-800'">{{ $invoices->where('balance_due', '>', 0)->count() }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'OVERDUE'"
                :class="filterStatus === 'OVERDUE' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>🚨 Overdue</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'OVERDUE' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-rose-100 text-rose-800'">{{ $invoices->filter(fn($i) => $i->balance_due > 0 && $i->due_date->isPast())->count() }}</span>
            </button>

            <button
                type="button"
                @click="filterStatus = 'PAID'"
                :class="filterStatus === 'PAID' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-[#F5F6F8] text-neutral-600 hover:text-neutral-900 border border-neutral-200/80'"
                class="px-4 py-2 rounded-full text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 font-display"
            >
                <span>✓ Fully Settled</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="filterStatus === 'PAID' ? 'bg-neutral-800 text-[#D7FF53]' : 'bg-emerald-100 text-emerald-800'">{{ $invoices->where('status', 'PAID')->count() }}</span>
            </button>
        </div>

        <!-- Search Bar & View Mode Toggle -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Search Input -->
            <div class="relative flex-1 md:w-64">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400 text-xs">🔍</span>
                <input
                    type="text"
                    x-model="searchQuery"
                    placeholder="Search Invoice #, customer, PO, LR..."
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
                    @click="setViewMode('table')"
                    :class="viewMode === 'table' ? 'bg-[#091315] text-[#D7FF53] shadow-xs font-black' : 'text-neutral-600 hover:text-neutral-900 font-bold'"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs transition-all cursor-pointer font-display"
                    title="Compact ERP Table View"
                >
                    <span>☰</span>
                    <span class="hidden sm:inline">Table</span>
                </button>
                <button
                    type="button"
                    @click="setViewMode('cards')"
                    :class="viewMode === 'cards' ? 'bg-[#091315] text-[#D7FF53] shadow-xs font-black' : 'text-neutral-600 hover:text-neutral-900 font-bold'"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs transition-all cursor-pointer font-display"
                    title="Ledger Cards View"
                >
                    <span>🗂️</span>
                    <span class="hidden sm:inline">Cards</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 1. MODERN INVOICE & AR LEDGER CARDS VIEW -->
    <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($invoices as $inv)
            @php
                $rel = formatRelativeDate($inv->due_date);
                $hasPayments = $inv->amount_received > 0 || $inv->receipts->count() > 0 || in_array($inv->status, ['PAID', 'PART_PAID']);
                $currentGstRate = ($inv->subtotal > 0 && $inv->gst_amount > 0) ? round(($inv->gst_amount / $inv->subtotal) * 100, 1) : 0;
                $effectiveShipment = $inv->commercialShipment ?? $inv->effective_shipment;
                $poList = array_filter(array_map('trim', explode(',', $inv->display_po_numbers)));
                $pctCollected = $inv->total_amount > 0 ? min(100, round(($inv->amount_received / $inv->total_amount) * 100)) : 0;
                $searchHaystack = strtolower("{$inv->invoice_number} {$inv->customer->company_name} {$inv->display_po_numbers} " . ($effectiveShipment?->lr_number ?? ''));
            @endphp
            <div
                x-show="matchesFilter('{{ $inv->status }}', {{ $rel['isOverdue'] ? 'true' : 'false' }}, '{{ addslashes($searchHaystack) }}')"
                class="bg-white rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] hover:shadow-md transition-all duration-200 flex flex-col justify-between overflow-hidden group"
            >
                <!-- Card Header: Invoice No + Status Pill -->
                <div class="p-4 pb-3 border-b border-neutral-100 bg-[#F5F6F8]/60 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-xl bg-[#091315] text-[#D7FF53] flex items-center justify-center text-xs shrink-0 border border-neutral-800 shadow-xs">
                            🧾
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono font-black text-sm text-neutral-900 block truncate">{{ $inv->invoice_number }}</span>
                                <a href="{{ route('invoices.pdf', $inv->id) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-[#091315] text-[#D7FF53] hover:bg-black transition-colors" title="Download Official Tax Invoice PDF">
                                    📄 PDF
                                </a>
                                @if($inv->documents->isNotEmpty() && $inv->documents->first()?->id)
                                    <a href="{{ route('documents.preview', $inv->documents->first()->id) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors" title="View Supporting Document">
                                        📎 Doc
                                    </a>
                                @endif
                            </div>
                            <span class="text-[10px] text-neutral-400 block font-mono">{{ $inv->invoice_date->format('d M Y') }}</span>
                        </div>
                    </div>

                    <!-- Status Pill -->
                    <span
                        class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shrink-0 border font-mono"
                        :class="{
                            'bg-emerald-50 text-emerald-800 border-emerald-200': '{{ $inv->status }}' === 'PAID',
                            'bg-rose-50 text-rose-700 border-rose-200 animate-pulse': {{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'true' : 'false' }},
                            'bg-amber-50 text-amber-800 border-amber-200': '{{ $inv->status }}' === 'PART_PAID',
                            'bg-[#F5F6F8] text-neutral-700 border-neutral-200/80': '{{ $inv->status }}' !== 'PAID' && '{{ $inv->status }}' !== 'PART_PAID' && !{{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'true' : 'false' }}
                        }"
                    >
                        @if($inv->status === 'PAID') ✓ Settled
                        @elseif($rel['isOverdue'] && $inv->balance_due > 0) 🚨 Overdue
                        @elseif($inv->status === 'PART_PAID') ⚡ Part Paid
                        @else ⏳ Issued
                        @endif
                    </span>
                </div>

                <!-- Card Body: Customer, Linked Logistics & Financials -->
                <div class="p-4 space-y-3.5 flex-1">
                    <!-- Customer & Due Date Banner -->
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('customers.show', $inv->customer_id) }}" class="font-black text-sm text-neutral-900 hover:text-neutral-600 truncate font-display" title="{{ $inv->customer->company_name }}">
                                {{ $inv->customer->company_name ?? 'Client' }}
                            </a>
                            <span class="text-[10px] font-bold shrink-0 px-2 py-0.5 rounded-full font-mono {{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200 font-extrabold' : 'bg-[#F5F6F8] text-neutral-600 border border-neutral-200/60' }}">
                                {{ $rel['text'] }}
                            </span>
                        </div>
                        <div class="text-[11px] text-neutral-500 mt-0.5 font-mono">
                            Due: <span class="font-bold text-neutral-700">{{ $inv->due_date->format('d M Y') }}</span>
                        </div>
                    </div>

                    <!-- Linked POs & Shipment LR Strip with 1-Click Track -->
                    <div class="p-3 bg-[#F5F6F8] rounded-2xl border border-neutral-200/60 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[10px] uppercase tracking-wider font-bold text-neutral-400 font-mono">Linked Order(s)</span>
                            <div class="flex items-center gap-1 flex-wrap justify-end">
                                @forelse($poList as $p)
                                    <span class="px-2 py-0.5 rounded-full bg-white text-neutral-800 font-mono font-bold text-[10px] border border-neutral-200/80 shadow-2xs">
                                        {{ trim($p) }}
                                    </span>
                                @empty
                                    <span class="text-neutral-400 font-mono text-[10px]">Direct PO</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-neutral-200/60">
                            <span class="text-[10px] uppercase tracking-wider font-bold text-neutral-400 font-mono">Transport Bilty</span>
                            @if($effectiveShipment)
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-[10px] text-neutral-900 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                        LR-{{ $effectiveShipment->lr_number }}
                                    </span>
                                    <button
                                        type="button"
                                        onclick="openLiveTracking({{ $effectiveShipment->id }}, '{{ $effectiveShipment->lr_number }}', '{{ addslashes($effectiveShipment->transporter) }}')"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-[#091315] text-[#D7FF53] hover:bg-neutral-800 shadow-2xs transition-colors cursor-pointer"
                                        title="Track live carrier radar"
                                    >
                                        <span>📍 Track</span>
                                    </button>
                                </div>
                            @else
                                <span class="text-xs text-neutral-400 italic">No LR linked</span>
                            @endif
                        </div>
                    </div>

                    <!-- Payment Progress Bar -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-bold text-neutral-600">Settlement Progress</span>
                            <span class="font-mono font-bold {{ $inv->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ $pctCollected }}% Collected
                            </span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-neutral-100 overflow-hidden flex">
                            <div class="h-full bg-[#091315] rounded-full transition-all duration-500" style="width: {{ $pctCollected }}%"></div>
                        </div>
                    </div>

                    <!-- 3-Tile Financial Breakdown -->
                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-neutral-100 text-center">
                        <div class="p-2 bg-[#F5F6F8] rounded-2xl border border-neutral-200/60">
                            <span class="text-[9px] uppercase tracking-wider text-neutral-400 font-bold block font-mono">Billed</span>
                            <span class="font-black text-neutral-900 text-xs mt-0.5 block truncate font-mono">
                                {{ formatINR($inv->total_amount) }}
                            </span>
                            @if($inv->freight_amount > 0)
                                <span class="text-[8px] text-emerald-700 font-bold block truncate font-mono">(+{{ formatINR($inv->freight_amount) }})</span>
                            @endif
                        </div>

                        <div class="p-2 bg-emerald-50/50 rounded-2xl border border-emerald-100">
                            <span class="text-[9px] uppercase tracking-wider text-emerald-700 font-bold block font-mono">Received</span>
                            <span class="font-black text-emerald-700 text-xs mt-0.5 block truncate font-mono">
                                {{ formatINR($inv->amount_received) }}
                            </span>
                        </div>

                        <div class="p-2 rounded-2xl border {{ $inv->balance_due > 0 ? 'bg-rose-50/50 border-rose-100' : 'bg-[#F5F6F8] border-neutral-200/60' }}">
                            <span class="text-[9px] uppercase tracking-wider font-bold block font-mono {{ $inv->balance_due > 0 ? 'text-rose-700' : 'text-neutral-400' }}">
                                Due
                            </span>
                            <span class="font-black text-xs mt-0.5 block truncate font-mono {{ $inv->balance_due > 0 ? 'text-rose-700' : 'text-neutral-400' }}">
                                {{ formatINR($inv->balance_due) }}
                            </span>
                        </div>
                    </div>

                    @can('invoices.view_profit')
                        @if($inv->gross_profit_amount > 0 || $inv->total_cost_amount > 0)
                            <div class="p-2 bg-blue-50/60 rounded-xl border border-blue-200/70 flex items-center justify-between text-xs font-mono">
                                <span class="text-[10px] uppercase font-bold text-blue-800">Margin / Profit</span>
                                <div class="text-right">
                                    <span class="font-black text-blue-900">{{ formatINR($inv->gross_profit_amount) }}</span>
                                    <span class="text-[10px] font-bold text-blue-700 ml-1">({{ $inv->gross_profit_percentage }}%)</span>
                                </div>
                            </div>
                        @endif
                    @endcan
                </div>

                <!-- Card Footer Actions -->
                <div class="p-3 bg-[#F5F6F8]/80 border-t border-neutral-100 flex items-center justify-between gap-2">
                    <div>
                        @if($inv->balance_due > 0)
                            <button
                                type="button"
                                @click="openReceiptModal({{ $inv->id }}, {{ $inv->balance_due }})"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] shadow-2xs transition-transform active:scale-95 cursor-pointer"
                            >
                                <span>💳</span>
                                <span>Record Payment</span>
                            </button>
                        @else
                            <span class="text-xs font-bold text-emerald-700 flex items-center gap-1 pl-1 font-mono">
                                <span>✓</span>
                                <span>100% Settled</span>
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5">
                        <a
                            href="{{ route('invoices.pdf', $inv->id) }}"
                            target="_blank"
                            class="px-2.5 py-1.5 rounded-full text-xs font-bold text-neutral-800 bg-white hover:bg-neutral-100 border border-neutral-200/80 shadow-2xs transition-colors flex items-center gap-1"
                            title="Download Tax Invoice PDF"
                        >
                            <span>📄</span>
                            <span>PDF</span>
                        </a>
                        <form action="{{ route('invoices.whatsapp', $inv->id) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="p-1.5 rounded-full text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 shadow-2xs transition-colors cursor-pointer text-xs font-bold inline-flex items-center"
                                title="Send Invoice PDF via WhatsApp"
                            >
                                💬
                            </button>
                        </form>
                        @if(!$hasPayments)
                            <button
                                type="button"
                                @click="openEditModal(@js($inv), {{ $currentGstRate }})"
                                class="px-3 py-1.5 rounded-full text-xs font-bold text-neutral-700 bg-white hover:bg-neutral-100 border border-neutral-200/80 shadow-2xs transition-colors cursor-pointer"
                                title="Edit Invoice Details"
                            >
                                ✏️ Edit
                            </button>

                            <form action="{{ route('invoices.destroy', $inv->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete Invoice {{ $inv->invoice_number }}? This will deduct {{ formatINR($inv->total_amount) }} from customer ledger.');">
                                @csrf
                                <button
                                    type="submit"
                                    class="p-1.5 rounded-full text-rose-600 bg-white hover:bg-rose-50 border border-rose-200 shadow-2xs transition-colors cursor-pointer text-xs font-bold"
                                    title="Delete Invoice"
                                >
                                    🗑️
                                </button>
                            </form>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold text-neutral-400 bg-neutral-100 border border-neutral-200/60 font-mono" title="Settled against receipts">
                                🔒 Locked
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white p-10 rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] text-center space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center text-xl mx-auto shadow-xs border border-neutral-800">🧾</div>
                <h4 class="font-bold text-sm text-neutral-900 font-display">No Invoices Issued</h4>
                <p class="text-xs text-neutral-500 max-w-sm mx-auto">Click "+ Generate Invoice" to create your first invoice.</p>
            </div>
        @endforelse
    </div>

    <!-- 2. STREAMLINED 11-COLUMN FULL-WIDTH ERP DATA TABLE VIEW -->
    <div x-show="viewMode === 'table'" class="bg-white rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] overflow-hidden w-full">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left text-xs border-collapse min-w-[1050px]">
                <thead>
                    <tr class="bg-[#091315] text-white border-b border-neutral-800">
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Invoice No.</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Date</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Customer</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Linked PO</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Shipment / LR</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono text-right">Invoice Amount</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono text-right">Received</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono text-right">Balance Due</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono">Due Date & Aging</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono text-center">Status</th>
                        <th class="py-3.5 px-4 font-extrabold text-[10px] uppercase tracking-wider font-mono text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($invoices as $inv)
                        @php
                            $rel = formatRelativeDate($inv->due_date);
                            $hasPayments = $inv->amount_received > 0 || $inv->receipts->count() > 0 || in_array($inv->status, ['PAID', 'PART_PAID']);
                            $currentGstRate = ($inv->subtotal > 0 && $inv->gst_amount > 0) ? round(($inv->gst_amount / $inv->subtotal) * 100, 1) : 0;
                            $effectiveShipment = $inv->commercialShipment ?? $inv->effective_shipment;
                            $poList = array_filter(array_map('trim', explode(',', $inv->display_po_numbers)));
                            $searchHaystack = strtolower("{$inv->invoice_number} {$inv->customer->company_name} {$inv->display_po_numbers} " . ($effectiveShipment?->lr_number ?? ''));
                        @endphp
                        <tr
                            x-show="matchesFilter('{{ $inv->status }}', {{ $rel['isOverdue'] ? 'true' : 'false' }}, '{{ addslashes($searchHaystack) }}')"
                            class="hover:bg-[#F5F6F8]/70 transition-colors"
                        >
                            <!-- 1. Invoice No. -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-black text-neutral-900 text-xs">{{ $inv->invoice_number }}</span>
                                    <a href="{{ route('invoices.pdf', $inv->id) }}" target="_blank" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#091315] text-[#D7FF53] hover:bg-black transition-colors" title="Official Tax Invoice PDF">
                                        📄 PDF
                                    </a>
                                    @if($inv->documents->isNotEmpty() && $inv->documents->first()?->id)
                                        <a href="{{ route('documents.preview', $inv->documents->first()->id) }}" target="_blank" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors" title="Supporting Document">
                                            📎
                                        </a>
                                    @endif
                                </div>
                            </td>

                            <!-- 2. Invoice Date -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="text-neutral-500 font-mono text-[11px]">{{ $inv->invoice_date->format('d M Y') }}</span>
                            </td>

                            <!-- 3. Customer -->
                            <td class="py-3.5 px-4">
                                <a href="{{ route('customers.show', $inv->customer_id) }}" class="font-bold text-neutral-900 hover:text-neutral-600 block transition-colors font-display text-xs truncate max-w-[200px]" title="{{ $inv->customer->company_name }}">
                                    {{ $inv->customer->company_name ?? '—' }}
                                </a>
                                @if($inv->customer->city)
                                    <span class="text-[10px] text-neutral-400 font-mono block mt-0.5">📍 {{ $inv->customer->city }}</span>
                                @endif
                            </td>

                            <!-- 4. Linked PO -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-1 flex-wrap">
                                    @forelse($poList as $p)
                                        <span class="px-2 py-0.5 rounded-full bg-[#F5F6F8] text-neutral-800 font-mono font-bold text-[10px] border border-neutral-200/60">
                                            {{ trim($p) }}
                                        </span>
                                    @empty
                                        <span class="text-neutral-400 font-mono text-[10px]">Direct PO</span>
                                    @endforelse
                                </div>
                            </td>

                            <!-- 5. Shipment / LR -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($effectiveShipment)
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-[10px] text-neutral-900 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                            LR-{{ $effectiveShipment->lr_number }}
                                        </span>
                                        <button
                                            type="button"
                                            onclick="openLiveTracking({{ $effectiveShipment->id }}, '{{ $effectiveShipment->lr_number }}', '{{ addslashes($effectiveShipment->transporter) }}')"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-[#091315] text-[#D7FF53] hover:bg-neutral-800 shadow-2xs cursor-pointer transition-colors"
                                            title="Track carrier live"
                                        >
                                            <span>📍 Track</span>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-neutral-400 italic text-[11px]">—</span>
                                @endif
                            </td>

                            <!-- 6. Invoice Amount -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="font-black text-neutral-900 text-xs font-mono block">{{ formatINR($inv->total_amount) }}</span>
                                <span class="text-[10px] text-neutral-400 font-mono block mt-0.5">Taxable: {{ formatINR($inv->subtotal) }}</span>
                                @if($inv->freight_amount > 0)
                                    <span class="text-[9px] text-emerald-700 font-mono font-bold block">(+{{ formatINR($inv->freight_amount) }} Frt)</span>
                                @endif
                                @can('invoices.view_profit')
                                    @if($inv->gross_profit_amount > 0)
                                        <span class="text-[9px] text-blue-700 font-mono font-bold block" title="Margin">
                                            +{{ formatINR($inv->gross_profit_amount) }} ({{ $inv->gross_profit_percentage }}%)
                                        </span>
                                    @endif
                                @endcan
                            </td>

                            <!-- 7. Amount Received -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="font-bold text-emerald-600 text-xs font-mono block">{{ formatINR($inv->amount_received) }}</span>
                            </td>

                            <!-- 8. Balance Due -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="font-black text-xs font-mono block {{ $inv->balance_due > 0 ? 'text-rose-600' : 'text-neutral-400' }}">
                                    {{ formatINR($inv->balance_due) }}
                                </span>
                            </td>

                            <!-- 9. Due Date & Aging -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-mono text-xs text-neutral-700 block">{{ $inv->due_date->format('d M Y') }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full font-mono mt-0.5 inline-block {{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-[#F5F6F8] text-neutral-600 border border-neutral-200/60' }}">
                                    {{ $rel['text'] }}
                                </span>
                            </td>

                            <!-- 10. Status -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span
                                    class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border font-mono inline-block"
                                    :class="{
                                        'bg-emerald-50 text-emerald-800 border-emerald-200': '{{ $inv->status }}' === 'PAID',
                                        'bg-rose-50 text-rose-700 border-rose-200 animate-pulse': {{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'true' : 'false' }},
                                        'bg-amber-50 text-amber-800 border-amber-200': '{{ $inv->status }}' === 'PART_PAID',
                                        'bg-[#F5F6F8] text-neutral-700 border-neutral-200/80': '{{ $inv->status }}' !== 'PAID' && '{{ $inv->status }}' !== 'PART_PAID' && !{{ $rel['isOverdue'] && $inv->balance_due > 0 ? 'true' : 'false' }}
                                    }"
                                >
                                    @if($inv->status === 'PAID') ✓ Settled
                                    @elseif($rel['isOverdue'] && $inv->balance_due > 0) 🚨 Overdue
                                    @elseif($inv->status === 'PART_PAID') ⚡ Part Paid
                                    @else ⏳ Awaiting
                                    @endif
                                </span>
                            </td>

                            <!-- 11. Actions -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('invoices.pdf', $inv->id) }}"
                                        target="_blank"
                                        class="p-1.5 rounded-full text-neutral-700 hover:bg-neutral-100 border border-neutral-200/80 text-xs font-bold cursor-pointer transition-colors"
                                        title="Download Tax Invoice PDF"
                                    >
                                        📄
                                    </a>
                                    <form action="{{ route('invoices.whatsapp', $inv->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-full text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-xs font-bold cursor-pointer inline-flex items-center"
                                            title="Send Invoice PDF via WhatsApp"
                                        >
                                            💬
                                        </button>
                                    </form>
                                    @if($inv->balance_due > 0)
                                        <button
                                            type="button"
                                            @click="openReceiptModal({{ $inv->id }}, {{ $inv->balance_due }})"
                                            class="px-2.5 py-1 rounded-full bg-[#D7FF53] text-[#091315] hover:bg-[#c8f043] border border-[#c8f043] text-[10px] font-extrabold shadow-2xs transition-transform active:scale-95 cursor-pointer flex items-center gap-1"
                                            title="Payment Receipt"
                                        >
                                            <span>💳</span>
                                            <span>Pay</span>
                                        </button>
                                    @endif

                                    @if(!$hasPayments)
                                        <button
                                            type="button"
                                            @click="openEditModal(@js($inv), {{ $currentGstRate }})"
                                            class="p-1.5 rounded-full text-neutral-700 hover:bg-neutral-100 border border-neutral-200/80 text-xs font-bold cursor-pointer transition-colors"
                                            title="Edit Invoice"
                                        >
                                            ✏️
                                        </button>

                                        <form action="{{ route('invoices.destroy', $inv->id) }}" method="POST" onsubmit="return confirm('Delete Invoice {{ $inv->invoice_number }}?');">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="p-1.5 rounded-full text-rose-600 hover:bg-rose-50 border border-rose-200 text-xs font-bold cursor-pointer transition-colors"
                                                title="Delete Invoice"
                                            >
                                                🗑️
                                            </button>
                                        </form>
                                    @else
                                        <span class="p-1 text-[10px] text-neutral-400" title="Settled against receipts">🔒</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="p-10 text-center text-neutral-400 italic font-medium">
                                No tax invoices found matching filter criteria. Click "+ Generate Invoice" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. PAYMENT RECEIPTS AUDIT TABLE -->
    <div class="bg-white rounded-3xl border border-neutral-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.02)] overflow-hidden w-full">
        <div class="p-4 lg:p-5 border-b border-neutral-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="text-base">💳</span>
                <h3 class="font-black text-sm text-neutral-900 font-display tracking-tight">Payment Receipts & Bank Settlements</h3>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/60 font-mono">
                    {{ $receipts->where('payment_mode', '!=', 'ADVANCE_OFFSET')->count() }} Bank Credits
                </span>
                @if($receipts->where('payment_mode', 'ADVANCE_OFFSET')->count() > 0)
                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200/60 font-mono">
                    {{ $receipts->where('payment_mode', 'ADVANCE_OFFSET')->count() }} Offsets
                </span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-[#F5F6F8] text-neutral-600 border-b border-neutral-200/80">
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Receipt Date</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Invoice No</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Customer</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider text-right">Amount Received</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Mode</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Bank UTR Ref</th>
                        <th class="py-3 px-4 font-bold font-mono text-[10px] uppercase tracking-wider">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($receipts as $r)
                        <tr class="hover:bg-[#F5F6F8]/70 transition-colors {{ $r->payment_mode === 'ADVANCE_OFFSET' ? 'bg-amber-50/20' : '' }}">
                            <td class="py-3 px-4 text-neutral-600 font-mono">{{ $r->receipt_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 font-mono font-black text-neutral-900">{{ $r->invoice->invoice_number ?? '—' }}</td>
                            <td class="py-3 px-4 font-bold text-neutral-900 font-display">{{ $r->customer->company_name ?? '—' }}</td>
                            <td class="py-3 px-4 text-right font-black font-mono {{ $r->payment_mode === 'ADVANCE_OFFSET' ? 'text-amber-800' : 'text-emerald-700' }}">{{ formatINR($r->amount_received) }}</td>
                            <td class="py-3 px-4 font-semibold text-neutral-700">
                                @if($r->payment_mode === 'ADVANCE_OFFSET')
                                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-mono font-bold tracking-tight">⚡ ADVANCE_OFFSET (Book Entry)</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-800 text-[10px] font-mono font-bold">{{ $r->payment_mode }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-neutral-600">{{ $r->reference_number ?: '—' }}</td>
                            <td class="py-3 px-4 text-neutral-500">{{ $r->remarks ?: 'Payment settled' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-neutral-400 italic">
                                No payment receipts logged yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Generate Invoice Modal (With Multi-PO Shipment Item Allocation) -->
    <template x-teleport="body">
        <div x-show="isNewInvoiceOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isNewInvoiceOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-3xl overflow-hidden max-h-[92vh] flex flex-col" x-data="invoiceModalForm()">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            🧾
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Generate New Tax Invoice</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Match against Shipment LR • Multi-PO Product Allocation</p>
                        </div>
                    </div>
                    <button @click="isNewInvoiceOpen = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors">✕</button>
                </div>

                <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto flex-1">
                    @csrf

                    <!-- Customer Account -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Customer Account *</label>
                        <select name="customer_id" x-model="selectedCustomerId" @change="onCustomerChange()" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-bold bg-white" required>
                            <option value="">-- Choose Customer --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->company_name }} ({{ $c->customer_code }}) · {{ $c->city }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Shipment LR Link (Optional) -->
                    <div x-show="selectedCustomerId">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-700">Linked Commercial Shipment / LR (Auto-fills Dispatched Items)</label>
                            <span class="text-[10px] text-brand-700 font-bold" x-text="getAvailableShipments().length + ' Un-invoiced LR(s) Available'"></span>
                        </div>
                        <select name="commercial_shipment_id" x-model="selectedShipmentId" @change="onShipmentChange()" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-amber-50/70 font-medium text-xs">
                            <option value="">-- Direct Invoice (No LR Link) --</option>
                            <template x-for="ship in getAvailableShipments()" :key="ship.id">
                                <option :value="ship.id" x-text="'LR-' + ship.lr_number + ' · ' + ship.transporter + ' (' + ship.shipment_date + ') — Un-invoiced: ₹' + Number(ship.remaining_material_amount).toLocaleString('en-IN') + (ship.unadjusted_advance > 0 ? ' [⚡ Advance: ₹' + Number(ship.unadjusted_advance).toLocaleString('en-IN') + ']' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <!-- DISPATCHED ITEMS ALLOCATION TABLE (When Shipment is selected) -->
                    <div x-show="selectedShipment && shipmentItems.length > 0" class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-black text-slate-900 block">Select Dispatched Products to Include in THIS Invoice:</span>
                                <span class="text-[11px] text-slate-500">Check items from this LR to bill on this Tax Invoice</span>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-brand-100 text-brand-800" x-text="getSelectedCount() + ' Selected'"></span>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(item, idx) in shipmentItems" :key="item.id">
                                <div class="p-3 rounded-xl border transition-all" :class="item.selected ? 'bg-white border-brand-400 ring-2 ring-brand-400/20 shadow-xs' : (item.remaining_amount <= 0 ? 'bg-slate-100/80 border-slate-200 opacity-60' : 'bg-slate-100/40 border-slate-200')">
                                    <div class="flex items-start justify-between gap-3">
                                        <label class="flex items-start gap-2.5 cursor-pointer flex-1">
                                            <input
                                                type="checkbox"
                                                x-model="item.selected"
                                                @change="recalculateFromShipmentItems()"
                                                :disabled="item.remaining_amount <= 0"
                                                class="mt-0.5 rounded text-brand-600 cursor-pointer"
                                            >
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-900 text-white font-mono font-bold text-[10px]" x-text="item.sales_order_number"></span>
                                                    <span class="font-bold text-slate-900" x-text="item.product_name"></span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 mt-0.5">
                                                    Shipped: <b x-text="item.quantity + ' KG'"></b> @ ₹<span x-text="item.rate"></span>/KG = ₹<span x-text="Number(item.total_value).toLocaleString('en-IN')"></span>
                                                    <span x-show="item.invoiced_amount > 0" class="text-amber-700 ml-1 font-semibold">(Already Invoiced: ₹<span x-text="Number(item.invoiced_amount).toLocaleString('en-IN')"></span>)</span>
                                                </div>
                                            </div>
                                        </label>

                                        <div class="text-right">
                                            <template x-if="item.remaining_amount > 0">
                                                <div>
                                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Invoicing Amount</span>
                                                    <span class="font-black text-slate-900" x-text="'₹' + Number(item.remaining_amount).toLocaleString('en-IN')"></span>
                                                </div>
                                            </template>
                                            <template x-if="item.remaining_amount <= 0">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">100% Invoiced ✓</span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Hidden form inputs for selected items -->
                                    <template x-if="item.selected && item.remaining_amount > 0">
                                        <div>
                                            <input type="hidden" :name="'selected_items[' + idx + '][commercial_shipment_item_id]'" :value="item.id">
                                            <input type="hidden" :name="'selected_items[' + idx + '][quantity]'" :value="item.remaining_quantity">
                                            <input type="hidden" :name="'selected_items[' + idx + '][rate]'" :value="item.rate">
                                            <input type="hidden" :name="'selected_items[' + idx + '][amount]'" :value="item.remaining_amount">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- Auto-detected Linked POs Display -->
                        <div class="pt-2 flex items-center justify-between text-xs border-t border-slate-200">
                            <span class="text-slate-600 font-medium">Auto-Linked Purchase Orders:</span>
                            <div class="flex items-center gap-1">
                                <template x-for="po in detectedPoNumbers" :key="po">
                                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-mono font-bold text-[11px]" x-text="po"></span>
                                </template>
                                <span x-show="detectedPoNumbers.length === 0" class="text-slate-400 italic">None selected</span>
                            </div>
                        </div>
                    </div>

                    <!-- DIRECT LINE ITEMS (When NO Shipment is selected) -->
                    <div class="space-y-3 pt-2" x-show="!selectedShipment && selectedCustomerId">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-black text-sm text-slate-900">Direct Product Line Items</span>
                                <p class="text-[11px] text-slate-500">Add products and link to buyer PO</p>
                            </div>
                            <button
                                type="button"
                                @click="addDirectItemRow()"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 flex items-center gap-1 cursor-pointer transition-all shadow-2xs"
                            >
                                + Add Line Item
                            </button>
                        </div>

                        <div class="space-y-2.5">
                            <template x-for="(item, idx) in directItems" :key="idx">
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 relative">
                                    <button
                                        type="button"
                                        x-show="directItems.length > 1"
                                        @click="removeDirectItemRow(idx)"
                                        class="absolute top-2 right-2 text-slate-400 hover:text-rose-600 font-black cursor-pointer text-sm"
                                        title="Remove Line"
                                    >
                                        ✕
                                    </button>

                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end pr-0 sm:pr-5">
                                        <div class="sm:col-span-5">
                                            <label class="block font-bold text-slate-700 mb-1 text-[11px]" x-text="'Item #' + (idx + 1) + ' Product *'"></label>
                                            <select :name="'items[' + idx + '][product_id]'" x-model="item.product_id" @change="onProductSelect(item, $event)" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg font-medium text-xs bg-white" required>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-rate="{{ $p->standard_rate }}">{{ $p->product_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block font-bold text-slate-700 mb-1 text-[11px]">Sales Order (PO)</label>
                                            <select :name="'items[' + idx + '][order_id]'" x-model="item.order_id" @change="recalculateDirectTotals(); calculateDueDate();" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg font-medium text-xs bg-white">
                                                <option value="">-- Direct Sale --</option>
                                                <template x-for="ord in getCustomerOrders()" :key="ord.id">
                                                    <option :value="ord.id" x-text="ord.order_number + (ord.po_number ? ' (' + ord.po_number + ')' : '')"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block font-bold text-slate-700 mb-1 text-[11px]">Qty (KG) *</label>
                                            <input type="number" step="0.5" :name="'items[' + idx + '][quantity]'" x-model="item.quantity" @input="recalculateDirectTotals()" placeholder="5000" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg font-bold text-xs bg-white text-right" required>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block font-bold text-slate-700 mb-1 text-[11px]">Rate/KG (₹) *</label>
                                            <input type="number" step="0.5" :name="'items[' + idx + '][rate]'" x-model="item.rate" @input="recalculateDirectTotals()" placeholder="120" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg font-bold text-xs bg-white text-right" required>
                                        </div>
                                    </div>

                                    <div class="flex justify-between items-center pt-1 text-[11px] text-slate-500 font-medium px-1">
                                        <span x-text="'Weight: ' + ((parseFloat(item.quantity) || 0) / 1000).toFixed(2) + ' MT (' + (parseFloat(item.quantity) || 0).toLocaleString('en-IN') + ' KG)'"></span>
                                        <span>
                                            Line Value: <b class="text-slate-900 font-black ml-1" x-text="'₹' + ((parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0)).toLocaleString('en-IN')"></b>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Invoice No, Date & Payment Terms -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Invoice Number *</label>
                            <input type="text" name="invoice_number" placeholder="e.g. INV-158" class="w-full px-3 py-2 border border-slate-300 rounded-xl font-mono font-bold text-xs" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Invoice Date *</label>
                            <input type="date" name="invoice_date" x-model="invoiceDate" @change="onInvoiceDateChange()" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-semibold text-slate-800" required>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block font-bold text-slate-700">Payment Due Date *</label>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded text-brand-800 bg-brand-50 border border-brand-200" x-show="creditTermsReason" x-text="creditTermsReason"></span>
                            </div>
                            <input type="date" name="due_date" x-model="dueDate" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-900" required>

                            <!-- Quick Credit Terms Preset Chips -->
                            <div class="flex items-center gap-1 mt-1.5 overflow-x-auto py-0.5">
                                <button type="button" @click="applyPresetDays(0, '⚡ Immediate / Advance')" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer" :class="activeCreditDays === 0 ? 'bg-slate-900 text-[#D7FF53] border-slate-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'" title="Immediate / 0 Days Advance">0D Adv</button>
                                <button type="button" @click="applyPresetDays(15, '15 Days Credit')" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer" :class="activeCreditDays === 15 ? 'bg-slate-900 text-[#D7FF53] border-slate-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'" title="15 Days Credit">15D</button>
                                <button type="button" @click="applyPresetDays(30, '30 Days Credit')" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer" :class="activeCreditDays === 30 ? 'bg-slate-900 text-[#D7FF53] border-slate-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'" title="30 Days Credit">30D</button>
                                <button type="button" @click="applyPresetDays(45, '45 Days Credit')" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer" :class="activeCreditDays === 45 ? 'bg-slate-900 text-[#D7FF53] border-slate-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'" title="45 Days Credit">45D</button>
                                <button type="button" @click="applyPresetDays(60, '60 Days Credit')" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer" :class="activeCreditDays === 60 ? 'bg-slate-900 text-[#D7FF53] border-slate-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'" title="60 Days Credit">60D</button>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden material_subtotal for direct invoicing -->
                    <input type="hidden" name="material_subtotal" :value="materialSubtotal">

                    <!-- Tax & Additional Charges Section -->
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">GST Tax Rate</label>
                                <select name="gst_rate" x-model="gstRate" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-xl bg-white font-bold text-xs">
                                    <option value="0">0% (Exempt/Export)</option>
                                    <option value="5" selected>5% (Dehydrated Spices Standard)</option>
                                    <option value="12">12%</option>
                                    <option value="18">18%</option>
                                </select>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-bold text-slate-700 text-xs">Freight (₹)</label>
                                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded" :class="freightMode === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'" x-text="'LR: ' + freightMode"></span>
                                </div>
                                <input type="number" step="1" min="0" name="freight_amount" x-model="freightAmount" placeholder="0" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-xl bg-white font-bold text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Other / Insurance (₹)</label>
                                <input type="number" step="1" min="0" name="other_charges" x-model="otherCharges" placeholder="0" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-xl bg-white font-bold text-xs">
                            </div>
                        </div>
                    </div>

                    <!-- Live Real-Time Breakdown Summary -->
                    <div class="p-4 bg-slate-900 text-white rounded-2xl space-y-1.5 text-xs shadow-inner">
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Material Subtotal:</span>
                            <span class="font-bold text-white font-mono" x-text="'₹' + (materialSubtotal ? Number(materialSubtotal).toLocaleString('en-IN') : '0')"></span>
                        </div>
                        <div x-show="parseFloat(freightAmount) > 0" class="flex items-center justify-between text-emerald-400">
                            <span>Freight / Transport Charges:</span>
                            <span class="font-bold text-emerald-400 font-mono" x-text="'+ ₹' + Number(freightAmount || 0).toLocaleString('en-IN')"></span>
                        </div>
                        <div x-show="parseFloat(otherCharges) > 0" class="flex items-center justify-between text-slate-400">
                            <span>Other Charges / Insurance:</span>
                            <span class="font-mono text-slate-200" x-text="'+ ₹' + Number(otherCharges || 0).toLocaleString('en-IN')"></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <span>GST Amount (<span x-text="gstRate"></span>%):</span>
                            <span class="font-mono text-slate-200" x-text="'₹' + getGstAmount().toLocaleString('en-IN', {maximumFractionDigits: 2})"></span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800 text-white font-black">
                            <span class="text-xs uppercase tracking-wider text-amber-300">Total Invoice Amount:</span>
                            <span class="text-base text-amber-400 font-mono" x-text="'₹' + getTotalAmount().toLocaleString('en-IN', {maximumFractionDigits: 0})"></span>
                        </div>
                        <!-- PO Advance Manual Adjustment Section -->
                        <div x-show="getAvailableAdvance() > 0" class="pt-3 border-t border-slate-800/80 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold text-[#D7FF53]">
                                    <span>⚡ PO Advance Adjustment:</span>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-[#D7FF53]/20 text-[#D7FF53] font-mono font-extrabold uppercase" x-text="advanceMode"></span>
                                </span>
                                <span class="text-[11px] font-mono text-slate-300">
                                    Available on PO: <b class="text-[#D7FF53]" x-text="'₹' + Number(getAvailableAdvance()).toLocaleString('en-IN')"></b>
                                </span>
                            </div>

                            <!-- Preset Mode Toggle Buttons -->
                            <div class="grid grid-cols-3 gap-1.5 bg-slate-950/70 p-1 rounded-xl border border-slate-800">
                                <button
                                    type="button"
                                    @click="setAdvanceMode('FULL')"
                                    class="py-1 px-2 rounded-lg font-bold text-[11px] transition-all cursor-pointer flex items-center justify-center gap-1"
                                    :class="advanceMode === 'FULL' ? 'bg-[#D7FF53] text-[#091315] shadow-xs' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'"
                                >
                                    <span>Full (100%)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setAdvanceMode('PROPORTIONAL')"
                                    class="py-1 px-2 rounded-lg font-bold text-[11px] transition-all cursor-pointer flex items-center justify-center gap-1"
                                    :class="advanceMode === 'PROPORTIONAL' ? 'bg-[#D7FF53] text-[#091315] shadow-xs' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'"
                                    title="Deduct advance proportional to partial shipment quantity/amount"
                                >
                                    <span>⚖️ Proportional</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setAdvanceMode('NONE')"
                                    class="py-1 px-2 rounded-lg font-bold text-[11px] transition-all cursor-pointer flex items-center justify-center gap-1"
                                    :class="advanceMode === 'NONE' ? 'bg-[#D7FF53] text-[#091315] shadow-xs' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'"
                                    title="Do not deduct advance on this invoice (reserve 100% for future shipments)"
                                >
                                    <span>None (₹0)</span>
                                </button>
                            </div>

                            <!-- Editable Custom Amount Field & Calculation Strip -->
                            <div class="bg-slate-950/60 p-2.5 rounded-xl border border-slate-800 space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-[11px] text-slate-300 font-medium">Advance to Deduct (₹):</label>
                                    <div class="relative w-36 sm:w-44">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold font-mono">₹</span>
                                        <input
                                            type="number"
                                            step="1"
                                            min="0"
                                            :max="Math.min(getTotalAmount(), getAvailableAdvance())"
                                            x-model="manualAdvanceAmount"
                                            @input="advanceMode = 'CUSTOM'; calculateDueDate();"
                                            placeholder="0"
                                            class="w-full pl-6 pr-2.5 py-1 bg-slate-900 border border-slate-700 rounded-lg text-right font-mono font-bold text-xs text-[#D7FF53] focus:border-[#D7FF53] focus:ring-1 focus:ring-[#D7FF53] outline-hidden"
                                        >
                                    </div>
                                </div>

                                <!-- Real-time Remaining Advance Notice -->
                                <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-800/60 font-mono">
                                    <span>Remaining PO Advance for next invoices:</span>
                                    <span class="font-bold text-slate-200" x-text="'₹' + Number(getRemainingPoAdvance()).toLocaleString('en-IN')"></span>
                                </div>
                            </div>

                            <!-- Hidden Form Input that sends the finalized deduction amount -->
                            <input type="hidden" name="advance_deduction_amount" :value="getAppliedAdvance()">

                            <!-- Summary Lines -->
                            <div class="flex items-center justify-between text-[#D7FF53] pt-1">
                                <span class="font-semibold">Less Advance Deducted:</span>
                                <span class="font-bold font-mono text-[#D7FF53]" x-text="'- ₹' + Number(getAppliedAdvance()).toLocaleString('en-IN', {maximumFractionDigits: 0})"></span>
                            </div>
                        </div>

                        <!-- Net Balance Due -->
                        <div x-show="getAvailableAdvance() > 0" class="flex items-center justify-between pt-1 border-t border-slate-800 text-emerald-400 font-black">
                            <span class="text-xs uppercase tracking-wider">Net Balance Due on Invoice:</span>
                            <span class="text-base font-mono" x-text="'₹' + Number(getNetBalanceDue()).toLocaleString('en-IN', {maximumFractionDigits: 0})"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Invoice Notes / Bank Account Details</label>
                        <textarea name="notes" rows="2" placeholder="e.g. Bank: HDFC Bank, Mahuva Branch • A/c No: 50200012345678 • IFSC: HDFC0000123" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs"></textarea>
                    </div>

                    <!-- Supporting Invoice Document Upload Section -->
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700 text-xs">
                                📎 Supporting Invoice Document <span class="text-slate-400 font-normal">(Optional — e.g. Scanned signed invoice, E-Way Bill, Delivery Challan)</span>
                            </label>
                            <span class="text-[10px] text-slate-500 font-mono">PDF, JPG, PNG up to 25MB</span>
                        </div>
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-slate-400 rounded-xl p-3 text-center transition-colors bg-white">
                            <input
                                type="file"
                                name="invoice_document"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                @change="selectedInvFile = $event.target.files[0] ? $event.target.files[0].name : null"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            >
                            <div class="flex items-center justify-center gap-2 text-slate-600">
                                <span class="text-base">📄</span>
                                <span class="text-xs font-semibold" x-text="selectedInvFile ? selectedInvFile : 'Click or drag supporting invoice / e-way bill document here'"></span>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400">Note: The official ERP Tax Invoice PDF is generated automatically upon creation and can be downloaded anytime.</p>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                        <button type="button" @click="isNewInvoiceOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                        <button
                            type="submit"
                            :disabled="!materialSubtotal || parseFloat(materialSubtotal) <= 0"
                            class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] disabled:opacity-50 disabled:cursor-not-allowed rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer"
                        >
                            Generate Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Combine Multiple Sales Orders into One Tax Invoice Modal -->
    <template x-teleport="body">
        <div x-show="isCombineModalOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isCombineModalOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col" x-data="{
                customerId: '',
                orders: @js($customerOrdersData),
                selectedOrders: [],
                freight: 0,
                gstRate: 5.0,
                tcsRate: 0,
                tdsRate: 0,
                invoiceNumber: 'INV-COMB-' + Math.floor(1000 + Math.random() * 9000),
                get availableOrders() {
                    return this.customerId && this.orders[this.customerId] ? this.orders[this.customerId].filter(o => o.remaining_amount > 0) : [];
                },
                get subtotal() {
                    let sum = 0;
                    this.availableOrders.forEach(o => {
                        if (this.selectedOrders.includes(o.id.toString()) || this.selectedOrders.includes(o.id)) {
                            sum += parseFloat(o.remaining_amount || 0);
                        }
                    });
                    return sum;
                },
                get total() {
                    const sub = this.subtotal + parseFloat(this.freight || 0);
                    const gst = sub * (parseFloat(this.gstRate || 0) / 100);
                    const tcs = sub * (parseFloat(this.tcsRate || 0) / 100);
                    const tds = sub * (parseFloat(this.tdsRate || 0) / 100);
                    return Math.max(0, sub + gst + tcs - tds);
                }
            }">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            📦
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Combine Multiple Sales Orders</h3>
                            <p class="text-[11px] text-neutral-400">Merge multiple confirmed orders into one single tax invoice with complete line-level traceability</p>
                        </div>
                    </div>
                    <button type="button" @click="isCombineModalOpen = false" class="text-neutral-400 hover:text-white cursor-pointer text-lg font-bold">✕</button>
                </div>

                <form action="{{ route('invoices.combine-orders') }}" method="POST" class="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Select Customer *</label>
                        <select
                            name="customer_id"
                            x-model="customerId"
                            @change="selectedOrders = []"
                            required
                            class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-medium focus:border-neutral-900 bg-white"
                        >
                            <option value="">-- Choose Customer --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->company_name }} ({{ $c->customer_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Available Orders with Checkboxes -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-neutral-700">Select Sales Orders to Combine *</label>
                            <span class="text-[10px] text-neutral-400 font-mono" x-text="availableOrders.length + ' Uninvoiced Orders Available'"></span>
                        </div>

                        <div class="border border-neutral-200 rounded-2xl p-3 max-h-48 overflow-y-auto space-y-2 bg-neutral-50/50">
                            <template x-if="availableOrders.length === 0">
                                <div class="text-center py-4 text-neutral-400">
                                    <span x-text="customerId ? 'No uninvoiced sales orders available for this customer.' : 'Please select a customer above to view open orders.'"></span>
                                </div>
                            </template>

                            <template x-for="order in availableOrders" :key="order.id">
                                <label class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-neutral-200 hover:border-neutral-400 cursor-pointer transition-colors">
                                    <div class="flex items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            name="sales_order_ids[]"
                                            :value="order.id"
                                            x-model="selectedOrders"
                                            class="rounded text-neutral-900 focus:ring-0 cursor-pointer"
                                        >
                                        <div>
                                            <span class="font-bold font-mono text-neutral-900" x-text="order.order_number"></span>
                                            <span class="text-[10px] text-neutral-400 block" x-text="'Terms: ' + (order.payment_terms || 'Credit')"></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold font-mono text-neutral-900" x-text="'₹' + Number(order.remaining_amount).toLocaleString('en-IN')"></span>
                                        <span class="text-[10px] text-neutral-400 block font-mono">Uninvoiced</span>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Invoice Details Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Invoice Number *</label>
                            <input
                                type="text"
                                name="invoice_number"
                                x-model="invoiceNumber"
                                required
                                class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-mono text-xs font-bold text-neutral-900 focus:border-neutral-900"
                            >
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Invoice Date *</label>
                            <input
                                type="date"
                                name="invoice_date"
                                value="{{ date('Y-m-d') }}"
                                required
                                class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-mono text-xs focus:border-neutral-900"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Freight (₹)</label>
                            <input
                                type="number"
                                step="0.01"
                                name="freight_amount"
                                x-model="freight"
                                class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-xl font-mono text-xs"
                            >
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">GST Rate (%)</label>
                            <input
                                type="number"
                                step="0.1"
                                name="gst_rate"
                                x-model="gstRate"
                                class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-xl font-mono text-xs"
                            >
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">TCS (%)</label>
                            <input
                                type="number"
                                step="0.001"
                                name="tcs_rate"
                                x-model="tcsRate"
                                placeholder="0.075"
                                class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-xl font-mono text-xs"
                            >
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">TDS (%)</label>
                            <input
                                type="number"
                                step="0.001"
                                name="tds_rate"
                                x-model="tdsRate"
                                placeholder="0.1"
                                class="w-full px-2.5 py-1.5 border border-neutral-200 rounded-xl font-mono text-xs"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Internal Notes</label>
                        <input
                            type="text"
                            name="notes"
                            placeholder="e.g. Combined shipment for Q3 dispatch schedule"
                            class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-xs"
                        >
                    </div>

                    <!-- Calculated Total Box -->
                    <div class="p-3 bg-neutral-900 text-white rounded-2xl flex items-center justify-between font-mono">
                        <div>
                            <span class="text-[10px] text-[#D7FF53] uppercase block font-bold">Estimated Grand Total</span>
                            <span class="text-xs text-neutral-400" x-text="selectedOrders.length + ' Orders Selected'"></span>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-black text-[#D7FF53]" x-text="'₹' + Number(total).toLocaleString('en-IN', { maximumFractionDigits: 2 })"></span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="isCombineModalOpen = false"
                            class="px-4 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="selectedOrders.length === 0"
                            class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] disabled:opacity-50 disabled:cursor-not-allowed rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer"
                        >
                            Generate Combined Tax Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Edit Tax Invoice Modal (Only for Unsettled Invoices) -->
    <template x-teleport="body">
        <div x-show="isEditModalOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isEditModalOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-xl overflow-hidden max-h-[90vh] flex flex-col" x-data="editInvoiceForm()">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            ✏️
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Edit Tax Invoice</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono" x-text="'Editing: ' + (editInvoice.invoice_number || '')"></p>
                        </div>
                    </div>
                    <button @click="isEditModalOpen = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors">✕</button>
                </div>

                <form :action="'/invoices/' + editInvoice.id + '/update'" method="POST" class="p-5 space-y-4 text-xs overflow-y-auto flex-1">
                    @csrf

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Customer Account</label>
                            <input type="text" :value="editInvoice.customer?.company_name" class="w-full px-3 py-2 border border-neutral-200 bg-neutral-100 rounded-xl font-bold" readonly>
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Invoice Number *</label>
                            <input type="text" name="invoice_number" x-model="editInvoice.invoice_number" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-mono font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Invoice Date *</label>
                            <input type="date" name="invoice_date" x-model="editInvoiceDate" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Payment Due Date *</label>
                            <input type="date" name="due_date" x-model="editDueDate" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                            <!-- Quick Credit Terms Preset Chips for Edit -->
                            <div class="flex items-center gap-1 mt-1.5 overflow-x-auto py-0.5">
                                <button type="button" @click="editDueDate = window.addDaysToDateString(editInvoiceDate, 0)" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-200 cursor-pointer" title="0 Days / Immediate">0D Adv</button>
                                <button type="button" @click="editDueDate = window.addDaysToDateString(editInvoiceDate, 15)" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-200 cursor-pointer" title="15 Days Credit">15D</button>
                                <button type="button" @click="editDueDate = window.addDaysToDateString(editInvoiceDate, 30)" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-200 cursor-pointer" title="30 Days Credit">30D</button>
                                <button type="button" @click="editDueDate = window.addDaysToDateString(editInvoiceDate, 45)" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-200 cursor-pointer" title="45 Days Credit">45D</button>
                                <button type="button" @click="editDueDate = window.addDaysToDateString(editInvoiceDate, 60)" class="px-1.5 py-0.5 rounded text-[10px] font-semibold border bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-200 cursor-pointer" title="60 Days Credit">60D</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Subtotal / Taxable Base (₹) *</label>
                            <input type="number" step="0.5" name="subtotal_amount" x-model="editSubtotal" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">GST Tax Rate</label>
                            <select name="gst_rate" x-model="editGstRate" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                                <option value="0">0% (Exempt)</option>
                                <option value="5">5% (Dehydrated Spices)</option>
                                <option value="12">12%</option>
                                <option value="18">18%</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Freight (₹)</label>
                            <input type="number" step="0.5" name="freight_amount" x-model="editFreight" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Other Charges (₹)</label>
                            <input type="number" step="0.5" name="other_charges" x-model="editOtherCharges" class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-neutral-700 mb-1">Invoice Notes / Payment Instructions</label>
                        <textarea name="notes" x-model="editInvoice.notes" rows="2" class="w-full p-2.5 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"></textarea>
                    </div>

                    <!-- Live Breakdown Preview -->
                    <div class="p-3.5 bg-[#091315] rounded-2xl text-xs space-y-1">
                        <div class="flex items-center justify-between text-neutral-400">
                            <span>GST Amount (<span x-text="editGstRate || 0"></span>%):</span>
                            <span class="font-bold text-white font-mono" x-text="'₹' + getEditGstAmount().toLocaleString('en-IN', {maximumFractionDigits: 2})"></span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-neutral-800 text-white font-black">
                            <span class="text-xs uppercase tracking-wider text-[#D7FF53]">Updated Total Invoice Value:</span>
                            <span class="text-base text-[#D7FF53] font-mono" x-text="'₹' + getEditTotalAmount().toLocaleString('en-IN', {maximumFractionDigits: 0})"></span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                        <button type="button" @click="isEditModalOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Payment Receipt Modal -->
    <template x-teleport="body">
        <div x-show="isNewReceiptOpen" x-cloak class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isNewReceiptOpen = false" class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-lg overflow-hidden flex flex-col max-h-[92vh]" x-data="receiptModalForm()">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center font-bold text-sm">
                            💰
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm tracking-tight text-white font-display">Payment Receipt</h3>
                            <p class="text-[11px] text-[#D7FF53] font-mono">Settles invoice & deducts customer outstanding</p>
                        </div>
                    </div>
                    <button @click="isNewReceiptOpen = false" class="text-neutral-400 hover:text-white cursor-pointer transition-colors">✕</button>
                </div>

                @if($invoices->where('balance_due', '>', 0)->isEmpty())
                    <div class="p-8 text-center space-y-3 text-xs">
                        <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-800 flex items-center justify-center text-xl mx-auto font-black">
                            ✓
                        </div>
                        <h4 class="font-black text-sm text-neutral-900">All Invoices Settled</h4>
                        <p class="text-neutral-500">There are no outstanding invoices awaiting payment.</p>
                    </div>
                @else
                    <form action="{{ route('invoices.receipt') }}" method="POST" class="p-4 sm:p-5 space-y-3 text-xs overflow-y-auto flex-1">
                        @csrf
                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Select Invoice to Settle *</label>
                            <select name="invoice_id" x-model="selectedInvoiceId" @change="onInvoiceChange()" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                                @foreach($invoices->where('balance_due', '>', 0) as $inv)
                                    <option value="{{ $inv->id }}" data-balance="{{ $inv->balance_due }}">
                                        {{ $inv->invoice_number }} · {{ $inv->customer->company_name }} (Balance Due: {{ formatINR($inv->balance_due) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-bold text-neutral-700">Amount Received (₹) *</label>
                                    <span class="text-[10px] text-[#091315] font-bold" x-text="'Max: ₹' + Number(currentBalance).toLocaleString('en-IN')"></span>
                                </div>
                                <input
                                    type="number"
                                    step="1"
                                    name="amount_received"
                                    :max="currentBalance"
                                    x-model="receiptAmount"
                                    placeholder="e.g. 646500"
                                    class="w-full px-3 py-2 border border-neutral-200 rounded-xl font-bold focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Payment Date *</label>
                                <input type="date" name="receipt_date" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden" required>
                            </div>
                        </div>

                        <!-- Warning if receipt exceeds balance -->
                        <div x-show="parseFloat(receiptAmount) > parseFloat(currentBalance)" class="p-2.5 bg-rose-50 border border-rose-200 rounded-xl text-[11px] text-rose-700 font-bold flex items-center gap-1.5">
                            <span>⚠️ Amount received exceeds the invoice balance of ₹<span x-text="Number(currentBalance).toLocaleString('en-IN')"></span>.</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Payment Mode *</label>
                                <select name="payment_mode" class="w-full px-3 py-2 border border-neutral-200 bg-white rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                                    <option value="UPI" selected>UPI Transfer</option>
                                    <option value="NEFT">NEFT Bank Transfer</option>
                                    <option value="RTGS">RTGS Bank Transfer</option>
                                    <option value="CHEQUE">Cheque</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-neutral-700 mb-1">Bank Reference No. / UTR</label>
                                <input type="text" name="reference_number" placeholder="e.g. UTR-HDFC-991823" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-neutral-700 mb-1">Remarks</label>
                            <input type="text" name="remarks" placeholder="e.g. Received full settlement against invoice" class="w-full px-3 py-2 border border-neutral-200 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                        </div>

                        <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                            <button type="button" @click="isNewReceiptOpen = false" class="px-5 py-2 font-bold text-neutral-600 hover:bg-neutral-100 rounded-full transition-colors cursor-pointer">Cancel</button>
                            <button
                                type="submit"
                                :disabled="parseFloat(receiptAmount) > parseFloat(currentBalance) || !receiptAmount"
                                class="px-5 py-2 font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] disabled:opacity-50 disabled:cursor-not-allowed rounded-full shadow-2xs border border-[#c8f043] transition-all cursor-pointer"
                            >
                                Save Receipt & Settle
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </template>
</div>

<script>
    function invoicesPageApp() {
        return {
            viewMode: (function() {
                try {
                    return localStorage.getItem('invoices_view_mode') || 'table';
                } catch (e) {
                    return 'table';
                }
            })(),
            filterStatus: 'ALL',
            searchQuery: '',
            isNewInvoiceOpen: false,
            isCombineModalOpen: false,
            isNewReceiptOpen: false,
            isEditModalOpen: false,
            editInvoice: {},
            editInvoiceDate: '',
            editDueDate: '',
            editSubtotal: '',
            editFreight: '',
            editOtherCharges: '',
            editGstRate: 5.0,

            setViewMode(mode) {
                this.viewMode = mode;
                try {
                    localStorage.setItem('invoices_view_mode', mode);
                } catch (e) {}
            },

            matchesFilter(status, isOverdue, searchHaystack) {
                if (this.filterStatus === 'UNPAID' && status === 'PAID') return false;
                if (this.filterStatus === 'OVERDUE' && (!isOverdue || status === 'PAID')) return false;
                if (this.filterStatus === 'PAID' && status !== 'PAID') return false;

                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase().trim();
                    return (searchHaystack || '').toLowerCase().includes(q);
                }

                return true;
            },

            openNewInvoiceModal() {
                this.isNewInvoiceOpen = true;
            },

            openReceiptModal(invoiceId, balance) {
                this.isNewReceiptOpen = true;
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('set-receipt-invoice', {
                        detail: { invoiceId: invoiceId, balance: balance }
                    }));
                    const select = document.querySelector('select[name="invoice_id"]');
                    if (select && invoiceId) {
                        select.value = invoiceId;
                        select.dispatchEvent(new Event('change'));
                    }
                });
            },

            openEditModal(invoice, gstRate) {
                this.editInvoice = invoice;
                this.editInvoiceDate = formatInputDate(invoice.invoice_date_formatted || invoice.invoice_date) || '{{ date("Y-m-d") }}';
                this.editDueDate = formatInputDate(invoice.due_date_formatted || invoice.due_date) || '{{ date("Y-m-d") }}';
                this.editSubtotal = invoice.material_subtotal > 0 ? invoice.material_subtotal : invoice.subtotal;
                this.editFreight = invoice.freight_amount || 0;
                this.editOtherCharges = 0;
                this.editGstRate = gstRate || 5.0;
                this.isEditModalOpen = true;
            }
        }
    }

    function editInvoiceForm() {
        return {
            getEditTaxableSubtotal() {
                const mat = parseFloat(this.editSubtotal) || 0;
                const frt = parseFloat(this.editFreight) || 0;
                const oth = parseFloat(this.editOtherCharges) || 0;
                return mat + frt + oth;
            },
            getEditGstAmount() {
                const taxable = this.getEditTaxableSubtotal();
                const rate = parseFloat(this.editGstRate) || 0;
                return taxable * (rate / 100);
            },
            getEditTotalAmount() {
                return this.getEditTaxableSubtotal() + this.getEditGstAmount();
            }
        }
    }

    function invoiceModalForm() {
        const shipmentDataList = @js($shipmentOptions);
        const customerOrdersMap = @js($customerOrdersData ?? []);
        const customersMap = @js($customersMap ?? []);
        const firstProdId = '{{ $products->first()?->id ?? "" }}';
        const firstProdRate = {{ $products->first()?->standard_rate ?? 120 }};
        const initialInvoiceDate = '{{ date('Y-m-d') }}';

        return {
            shipmentList: shipmentDataList,
            customerOrdersMap: customerOrdersMap,
            customersMap: customersMap,
            selectedCustomerId: '{{ $customers->first()?->id ?? "" }}',
            selectedShipmentId: '',
            selectedShipment: null,
            shipmentItems: [],
            selectedInvFile: null,
            invoiceDate: initialInvoiceDate,
            dueDate: '',
            activeCreditDays: 30,
            creditTermsReason: '',
            directItems: [
                { product_id: firstProdId, order_id: '', quantity: 5000, rate: firstProdRate }
            ],
            detectedPoNumbers: [],
            materialSubtotal: 0,
            gstRate: 5.0,
            freightAmount: '',
            otherCharges: '',
            freightMode: 'TO_PAY',
            manualAdvanceAmount: null,
            advanceMode: 'FULL',

            init() {
                // Initialize default subtotal for direct items
                this.recalculateDirectTotals();
                this.calculateDueDate();
            },

            getAvailableShipments() {
                if (!this.selectedCustomerId) {
                    return this.shipmentList;
                }
                return this.shipmentList.filter(s => String(s.customer_id) === String(this.selectedCustomerId));
            },

            getCustomerOrders() {
                if (!this.selectedCustomerId) return [];
                return this.customerOrdersMap[this.selectedCustomerId] || [];
            },

            onCustomerChange() {
                this.manualAdvanceAmount = null;
                this.advanceMode = 'FULL';
                // If the selected shipment doesn't belong to this customer, reset shipment
                if (this.selectedShipment && String(this.selectedShipment.customer_id) !== String(this.selectedCustomerId)) {
                    this.selectedShipmentId = '';
                    this.selectedShipment = null;
                    this.shipmentItems = [];
                    this.detectedPoNumbers = [];
                    this.recalculateDirectTotals();
                } else {
                    this.calculateDueDate();
                }
            },

            onShipmentChange() {
                this.manualAdvanceAmount = null;
                this.advanceMode = 'FULL';
                if (!this.selectedShipmentId) {
                    this.selectedShipment = null;
                    this.shipmentItems = [];
                    this.detectedPoNumbers = [];
                    this.freightMode = 'TO_PAY';
                    this.freightAmount = '';
                    this.recalculateDirectTotals();
                    this.calculateDueDate();
                    return;
                }

                const found = this.shipmentList.find(s => s.id == this.selectedShipmentId);
                if (found) {
                    this.selectedShipment = found;
                    this.selectedCustomerId = found.customer_id;
                    this.freightMode = found.freight_payment_type;

                    if (found.freight_payment_type === 'PAID' && found.freight_amount > 0) {
                        this.freightAmount = found.freight_amount;
                    } else {
                        this.freightAmount = '';
                    }

                    // Clone items with default selection
                    this.shipmentItems = found.items.map(it => ({
                        ...it,
                        selected: it.remaining_amount > 0,
                    }));

                    this.recalculateFromShipmentItems();
                    this.calculateDueDate();
                }
            },

            recalculateFromShipmentItems() {
                let sub = 0;
                const poSet = new Set();

                this.shipmentItems.forEach(it => {
                    if (it.selected && it.remaining_amount > 0) {
                        sub += it.remaining_amount;
                        if (it.sales_order_number) {
                            poSet.add(it.sales_order_number);
                        }
                    }
                });

                this.materialSubtotal = sub;
                this.detectedPoNumbers = Array.from(poSet);
                this.calculateDueDate();
            },

            getSelectedCount() {
                return this.shipmentItems.filter(it => it.selected && it.remaining_amount > 0).length;
            },

            // Direct line items methods
            addDirectItemRow() {
                this.directItems.push({
                    product_id: firstProdId,
                    order_id: '',
                    quantity: 1000,
                    rate: firstProdRate
                });
                this.recalculateDirectTotals();
            },

            removeDirectItemRow(idx) {
                if (this.directItems.length > 1) {
                    this.directItems.splice(idx, 1);
                    this.recalculateDirectTotals();
                }
            },

            onProductSelect(item, event) {
                const opt = event.target.selectedOptions[0];
                if (opt && opt.getAttribute('data-rate')) {
                    item.rate = parseFloat(opt.getAttribute('data-rate'));
                }
                this.recalculateDirectTotals();
            },

            recalculateDirectTotals() {
                if (this.selectedShipment) return; // In shipment mode, recalculateFromShipmentItems handles this
                let total = 0;
                this.directItems.forEach(it => {
                    const q = parseFloat(it.quantity) || 0;
                    const r = parseFloat(it.rate) || 0;
                    total += (q * r);
                });
                this.materialSubtotal = total;
                this.calculateDueDate();
            },

            getTaxableSubtotal() {
                const mat = parseFloat(this.materialSubtotal) || 0;
                const frt = parseFloat(this.freightAmount) || 0;
                const oth = parseFloat(this.otherCharges) || 0;
                return mat + frt + oth;
            },

            getGstAmount() {
                const taxable = this.getTaxableSubtotal();
                const rate = parseFloat(this.gstRate) || 0;
                return taxable * (rate / 100);
            },

            getTotalAmount() {
                return this.getTaxableSubtotal() + this.getGstAmount();
            },

            getAvailableAdvance() {
                if (this.selectedShipment) {
                    return parseFloat(this.selectedShipment.unadjusted_advance) || 0;
                }
                let total = 0;
                const countedOrders = new Set();
                const ordersList = this.getCustomerOrders();
                this.directItems.forEach(it => {
                    if (it.order_id && !countedOrders.has(it.order_id)) {
                        countedOrders.add(it.order_id);
                        const ord = ordersList.find(o => String(o.id) === String(it.order_id));
                        if (ord) {
                            total += (parseFloat(ord.unadjusted_advance) || 0);
                        }
                    }
                });
                return total;
            },

            getProportionalAdvance() {
                const avail = this.getAvailableAdvance();
                if (avail <= 0) return 0;

                const currentMat = parseFloat(this.materialSubtotal) || 0;
                if (currentMat <= 0) return Math.round(avail / 2);

                if (this.selectedShipment) {
                    const totalOrderVal = parseFloat(this.selectedShipment.total_order_value) || 0;
                    if (totalOrderVal > 0 && currentMat < totalOrderVal) {
                        return Math.round(avail * (currentMat / totalOrderVal));
                    }
                    const shipMatVal = parseFloat(this.selectedShipment.material_value) || 0;
                    if (shipMatVal > 0 && currentMat < shipMatVal) {
                        return Math.round(avail * (currentMat / shipMatVal));
                    }
                }

                // If direct items mode
                const ordersList = this.getCustomerOrders();
                let totalOrderVal = 0;
                this.directItems.forEach(it => {
                    if (it.order_id) {
                        const ord = ordersList.find(o => String(o.id) === String(it.order_id));
                        if (ord) totalOrderVal += (parseFloat(ord.total_amount) || 0);
                    }
                });
                if (totalOrderVal > 0 && currentMat < totalOrderVal) {
                    return Math.round(avail * (currentMat / totalOrderVal));
                }

                return Math.round(avail / 2);
            },

            setAdvanceMode(mode) {
                this.advanceMode = mode;
                const avail = this.getAvailableAdvance();
                const total = this.getTotalAmount();

                if (mode === 'FULL') {
                    this.manualAdvanceAmount = Math.min(total, avail);
                } else if (mode === 'PROPORTIONAL') {
                    this.manualAdvanceAmount = Math.min(total, this.getProportionalAdvance());
                } else if (mode === 'NONE') {
                    this.manualAdvanceAmount = 0;
                }
                this.calculateDueDate();
            },

            getAppliedAdvance() {
                const avail = this.getAvailableAdvance();
                if (avail <= 0) return 0;
                const total = this.getTotalAmount();

                if (this.manualAdvanceAmount === null || this.manualAdvanceAmount === '' || this.manualAdvanceAmount === undefined) {
                    return Math.min(total, avail);
                }
                const entered = parseFloat(this.manualAdvanceAmount) || 0;
                return Math.max(0, Math.min(entered, total, avail));
            },

            getRemainingPoAdvance() {
                const avail = this.getAvailableAdvance();
                const applied = this.getAppliedAdvance();
                return Math.max(0, avail - applied);
            },

            getNetBalanceDue() {
                const total = this.getTotalAmount();
                const applied = this.getAppliedAdvance();
                return Math.max(0, total - applied);
            },

            parseTermsString(term) {
                if (!term || typeof term !== 'string') return null;
                const str = term.trim().toLowerCase();

                if (str.includes('100% advance') || str.includes('immediate') || str.includes('cash on delivery') || str.includes('cod') || str.includes('advance payment') || str.includes('against delivery')) {
                    return 0;
                }

                const dayMatch = str.match(/(\d+)\s*(?:days?|day)/i);
                if (dayMatch && dayMatch[1]) {
                    return parseInt(dayMatch[1], 10);
                }

                const netMatch = str.match(/net\s*(\d+)/i);
                if (netMatch && netMatch[1]) {
                    return parseInt(netMatch[1], 10);
                }

                const numOnly = str.match(/^\d+$/);
                if (numOnly) {
                    return parseInt(numOnly[0], 10);
                }

                return null;
            },

            detectCreditDays() {
                // 1. Advance coverage check: If Net Balance Due is 0 and applied advance > 0
                const netDue = this.getNetBalanceDue();
                const appliedAdv = this.getAppliedAdvance();
                if (netDue <= 0 && appliedAdv > 0) {
                    return { days: 0, reason: '⚡ 100% Advance Settled' };
                }

                // 2. Check linked shipment or linked PO terms
                if (this.selectedShipment) {
                    if (this.selectedShipment.order_payment_terms) {
                        const parsed = this.parseTermsString(this.selectedShipment.order_payment_terms);
                        if (parsed !== null) {
                            return { days: parsed, reason: `PO Terms: ${this.selectedShipment.order_payment_terms}` };
                        }
                    }
                    const itemWithTerms = this.shipmentItems.find(it => it.selected && it.sales_order_payment_terms);
                    if (itemWithTerms && itemWithTerms.sales_order_payment_terms) {
                        const parsed = this.parseTermsString(itemWithTerms.sales_order_payment_terms);
                        if (parsed !== null) {
                            return { days: parsed, reason: `PO Terms: ${itemWithTerms.sales_order_payment_terms}` };
                        }
                    }
                } else {
                    const ordersList = this.getCustomerOrders();
                    for (const it of this.directItems) {
                        if (it.order_id) {
                            const ord = ordersList.find(o => String(o.id) === String(it.order_id));
                            if (ord && ord.payment_terms) {
                                const parsed = this.parseTermsString(ord.payment_terms);
                                if (parsed !== null) {
                                    return { days: parsed, reason: `PO Terms: ${ord.payment_terms}` };
                                }
                            }
                        }
                    }
                }

                // 3. Customer account default credit terms
                const custId = String(this.selectedCustomerId || '');
                if (custId && this.customersMap && this.customersMap[custId]) {
                    const cust = this.customersMap[custId];
                    const days = cust.payment_terms_days !== undefined && cust.payment_terms_days !== null ? parseInt(cust.payment_terms_days, 10) : 30;
                    return { days: days, reason: `Client Terms: ${days} Days Credit` };
                }

                return { days: 30, reason: 'Default (30 Days Credit)' };
            },

            calculateDueDate() {
                const { days, reason } = this.detectCreditDays();
                this.activeCreditDays = days;
                this.creditTermsReason = reason;
                const baseDate = this.invoiceDate || '{{ date('Y-m-d') }}';
                this.dueDate = window.addDaysToDateString ? window.addDaysToDateString(baseDate, days) : baseDate;
            },

            applyPresetDays(days, reason = null) {
                this.activeCreditDays = days;
                this.creditTermsReason = reason || `${days} Days Credit`;
                const baseDate = this.invoiceDate || '{{ date('Y-m-d') }}';
                this.dueDate = window.addDaysToDateString ? window.addDaysToDateString(baseDate, days) : baseDate;
            },

            onInvoiceDateChange() {
                const baseDate = this.invoiceDate || '{{ date('Y-m-d') }}';
                this.dueDate = window.addDaysToDateString ? window.addDaysToDateString(baseDate, this.activeCreditDays) : baseDate;
            }
        }
    }

    function receiptModalForm() {
        const firstUnpaid = {{ $invoices->where('balance_due', '>', 0)->first()?->balance_due ?? 0 }};
        return {
            selectedInvoiceId: '{{ $invoices->where('balance_due', '>', 0)->first()?->id ?? "" }}',
            currentBalance: firstUnpaid,
            receiptAmount: '',
            init() {
                window.addEventListener('set-receipt-invoice', (e) => {
                    if (e.detail && e.detail.invoiceId) {
                        this.selectedInvoiceId = String(e.detail.invoiceId);
                        this.currentBalance = parseFloat(e.detail.balance || 0);
                        this.receiptAmount = '';
                    }
                });
            },
            onInvoiceChange() {
                const selectElem = document.querySelector('select[name="invoice_id"]');
                const opt = selectElem ? selectElem.selectedOptions[0] : null;
                if (opt) {
                    this.currentBalance = parseFloat(opt.getAttribute('data-balance') || 0);
                }
            }
        }
    }
</script>
@endsection