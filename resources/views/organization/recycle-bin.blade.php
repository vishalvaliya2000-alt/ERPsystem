@extends('layouts.app')

@section('title', 'Recycle Bin & Data Recovery')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{
    activeTab: 'all',
    restoreTarget: null,
    purgeTarget: null,
    showRestoreModal: false,
    showPurgeModal: false,
    confirmRestore(type, id, name) {
        this.restoreTarget = { type, id, name };
        this.showRestoreModal = true;
    },
    confirmPurge(type, id, name) {
        this.purgeTarget = { type, id, name };
        this.showPurgeModal = true;
    }
}">
    <!-- Top Navigation Tabs (Organization Hub) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold border-b border-neutral-200">
        <a href="{{ route('organization.settings') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏢 Organization Profile
        </a>
        <a href="{{ route('organization.team') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            👥 Team & Staff Access
        </a>
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏛️ 4 Fixed Departments
        </a>
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🛡️ Security Audit Logs
        </a>
        <a href="{{ route('organization.companies.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏭 Companies & Operating Firms
        </a>
        <a href="{{ route('recycle-bin.index') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-[#D7FF53] shadow-xs whitespace-nowrap">
            🗑️ Recycle Bin
        </a>
    </div>

    <!-- Header Banner -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-neutral-900 text-[#D7FF53] font-black text-xl flex items-center justify-center shadow-md shrink-0">
                🗑️
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-neutral-900 tracking-tight font-display">Recycle Bin & Safe Data Recovery</h1>
                <p class="text-xs text-neutral-500">Safely recover accidentally deleted sales orders, tax invoices, customer accounts, and master products.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 rounded-full text-xs font-bold font-mono bg-neutral-100 text-neutral-800 border border-neutral-200">
                {{ $totalTrashedCount }} Deleted {{ Str::plural('Record', $totalTrashedCount) }}
            </span>
        </div>
    </div>

    <!-- Module Subtabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold">
        <button
            type="button"
            @click="activeTab = 'all'"
            :class="activeTab === 'all' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>All Items</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-800 text-[#D7FF53]">{{ $totalTrashedCount }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'orders'"
            :class="activeTab === 'orders' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Sales Orders</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedOrders->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'invoices'"
            :class="activeTab === 'invoices' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Tax Invoices</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedInvoices->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'customers'"
            :class="activeTab === 'customers' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Customers</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedCustomers->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'products'"
            :class="activeTab === 'products' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Products</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedProducts->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'shipments'"
            :class="activeTab === 'shipments' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Dispatches / LR</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedShipments->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'quotations'"
            :class="activeTab === 'quotations' ? 'bg-neutral-900 text-[#D7FF53] shadow-xs' : 'bg-white text-neutral-600 border border-neutral-200/80 hover:bg-neutral-50'"
            class="px-4 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
        >
            <span>Quotations</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-neutral-100 text-neutral-700">{{ $trashedQuotations->count() }}</span>
        </button>
    </div>

    <!-- Trashed Records Table / List -->
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
        @if($totalTrashedCount === 0)
            <div class="p-12 text-center space-y-3">
                <span class="text-4xl">✨</span>
                <h3 class="text-base font-bold text-neutral-900">Recycle Bin is Empty</h3>
                <p class="text-xs text-neutral-500 max-w-sm mx-auto">No deleted records found. Deleted sales transactions, invoices, and customer accounts will safely appear here for recovery.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-neutral-400 font-mono uppercase text-[10px]">
                            <th class="p-3.5 pl-6">Module</th>
                            <th class="p-3.5">Record Identifier</th>
                            <th class="p-3.5">Details</th>
                            <th class="p-3.5">Deleted At</th>
                            <th class="p-3.5 pr-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 font-sans">
                        <!-- Trashed Sales Orders -->
                        @foreach($trashedOrders as $order)
                            <tr x-show="activeTab === 'all' || activeTab === 'orders'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-[10px]">Sales Order</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    {{ $order->order_number }}
                                    @if($order->po_number)
                                        <span class="text-[10px] text-neutral-400 block font-normal">PO: {{ $order->po_number }}</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div class="font-bold text-neutral-800">{{ $order->customer?->company_name ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">Val: {{ formatINR($order->total_amount) }}</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $order->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('sales_order', {{ $order->id }}, '{{ addslashes($order->order_number) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('sales_order', {{ $order->id }}, '{{ addslashes($order->order_number) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Trashed Invoices -->
                        @foreach($trashedInvoices as $inv)
                            <tr x-show="activeTab === 'all' || activeTab === 'invoices'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px]">Tax Invoice</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    {{ $inv->invoice_number }}
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div class="font-bold text-neutral-800">{{ $inv->customer?->company_name ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">Total: {{ formatINR($inv->total_amount) }}</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $inv->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('invoice', {{ $inv->id }}, '{{ addslashes($inv->invoice_number) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('invoice', {{ $inv->id }}, '{{ addslashes($inv->invoice_number) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Trashed Customers -->
                        @foreach($trashedCustomers as $cust)
                            <tr x-show="activeTab === 'all' || activeTab === 'customers'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 text-[10px]">Customer</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    {{ $cust->company_name }}
                                    <span class="text-[10px] text-neutral-400 block font-normal">{{ $cust->customer_code }}</span>
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div>{{ $cust->city ?? 'Gujarat' }} ({{ $cust->state ?? 'India' }})</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">GSTIN: {{ $cust->gst_number ?? 'Unregistered' }}</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $cust->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('customer', {{ $cust->id }}, '{{ addslashes($cust->company_name) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('customer', {{ $cust->id }}, '{{ addslashes($cust->company_name) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Trashed Products -->
                        @foreach($trashedProducts as $prod)
                            <tr x-show="activeTab === 'all' || activeTab === 'products'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px]">Product</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    {{ $prod->product_name }}
                                    <span class="text-[10px] text-neutral-400 block font-normal">SKU: {{ $prod->product_code }}</span>
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div>Category: {{ $prod->category?->name ?? 'Agro' }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">HSN: {{ $prod->hsn_code }} · Base: {{ formatINR($prod->standard_rate) }}/{{ $prod->uom ?? 'KG' }}</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $prod->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('product', {{ $prod->id }}, '{{ addslashes($prod->product_name) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('product', {{ $prod->id }}, '{{ addslashes($prod->product_name) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Trashed Shipments -->
                        @foreach($trashedShipments as $ship)
                            <tr x-show="activeTab === 'all' || activeTab === 'shipments'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200 text-[10px]">Dispatch LR</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    LR-{{ $ship->lr_number }}
                                    <span class="text-[10px] text-neutral-400 block font-normal">{{ $ship->transporter }}</span>
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div>Dest: {{ $ship->destination ?? 'Door Delivery' }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">Freight: {{ formatINR($ship->freight_amount) }} ({{ $ship->freight_payment_type }})</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $ship->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('commercial_shipment', {{ $ship->id }}, 'LR-{{ addslashes($ship->lr_number) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('commercial_shipment', {{ $ship->id }}, 'LR-{{ addslashes($ship->lr_number) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Trashed Quotations -->
                        @foreach($trashedQuotations as $quo)
                            <tr x-show="activeTab === 'all' || activeTab === 'quotations'" class="hover:bg-neutral-50/60 transition-colors">
                                <td class="p-3.5 pl-6 font-bold text-neutral-500 font-mono">
                                    <span class="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-700 border border-neutral-200 text-[10px]">Quotation</span>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-neutral-900">
                                    {{ $quo->quotation_number }}
                                </td>
                                <td class="p-3.5 text-neutral-600">
                                    <div>{{ $quo->recipient_company ?? ($quo->customer?->company_name ?? 'Client') }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">Amount: {{ formatINR($quo->total_amount) }}</div>
                                </td>
                                <td class="p-3.5 text-neutral-500 font-mono text-[11px]">
                                    {{ $quo->deleted_at?->format('d M Y, h:i A') }}
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="confirmRestore('quotation', {{ $quo->id }}, '{{ addslashes($quo->quotation_number) }}')"
                                            class="px-3 py-1 rounded-full text-xs font-bold text-neutral-900 bg-[#D7FF53] hover:bg-[#c8f043] border border-[#c8f043] cursor-pointer shadow-2xs transition-all"
                                        >
                                            ↩ Restore
                                        </button>
                                        @if(in_array(auth()->user()->role ?? '', ['owner', 'admin']))
                                            <button
                                                type="button"
                                                @click="confirmPurge('quotation', {{ $quo->id }}, '{{ addslashes($quo->quotation_number) }}')"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 cursor-pointer transition-all"
                                            >
                                                ✕ Purge
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Restore Confirmation Modal -->
    <div
        x-show="showRestoreModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        @keydown.escape.window="showRestoreModal = false"
    >
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-neutral-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg font-bold">
                    ↩
                </div>
                <div>
                    <h3 class="font-bold text-base text-neutral-900">Restore Record</h3>
                    <p class="text-xs text-neutral-500">Restore this item back to active ERP operations</p>
                </div>
            </div>

            <p class="text-xs text-neutral-700">
                Are you sure you want to restore <span class="font-bold font-mono text-neutral-900" x-text="restoreTarget?.name"></span>? It will become visible and active immediately.
            </p>

            <form method="POST" action="{{ route('recycle-bin.restore') }}" class="flex items-center justify-end gap-2 pt-2">
                @csrf
                <input type="hidden" name="model_type" :value="restoreTarget?.type">
                <input type="hidden" name="id" :value="restoreTarget?.id">

                <button
                    type="button"
                    @click="showRestoreModal = false"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-5 py-2 rounded-xl text-xs font-extrabold bg-[#091315] text-[#D7FF53] hover:bg-black shadow-xs cursor-pointer transition-all"
                >
                    Confirm Restore
                </button>
            </form>
        </div>
    </div>

    <!-- Purge Confirmation Modal -->
    <div
        x-show="showPurgeModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        @keydown.escape.window="showPurgeModal = false"
    >
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-rose-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-700 flex items-center justify-center text-lg font-bold">
                    ⚠️
                </div>
                <div>
                    <h3 class="font-bold text-base text-rose-900">Permanent Deletion</h3>
                    <p class="text-xs text-neutral-500">This action cannot be undone</p>
                </div>
            </div>

            <p class="text-xs text-neutral-700">
                Are you sure you want to <span class="font-bold text-rose-600">permanently delete</span> <span class="font-bold font-mono text-neutral-900" x-text="purgeTarget?.name"></span>? The record and all associated logs will be purged permanently.
            </p>

            <form method="POST" action="{{ route('recycle-bin.force-delete') }}" class="flex items-center justify-end gap-2 pt-2">
                @csrf
                <input type="hidden" name="model_type" :value="purgeTarget?.type">
                <input type="hidden" name="id" :value="purgeTarget?.id">

                <button
                    type="button"
                    @click="showPurgeModal = false"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-100 transition-colors cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-5 py-2 rounded-xl text-xs font-extrabold bg-rose-600 text-white hover:bg-rose-700 shadow-xs cursor-pointer transition-all"
                >
                    Permanently Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
