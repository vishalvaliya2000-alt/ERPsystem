@extends('layouts.app')

@section('title', 'Batch & Serial Traceability Audit')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Reports Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-2 overflow-x-auto text-xs font-bold">
            <a href="{{ route('reports.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
                📊 Reports Hub
            </a>
            <a href="{{ route('reports.balance-sheet') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors">
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
            <a href="{{ route('reports.batch-trace') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm">
                🔍 Batch Traceability
            </a>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('reports.batch-trace.pdf', ['search' => $searchTerm]) }}"
               class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📄</span> Export PDF
            </a>
            <a href="{{ route('reports.batch-trace.excel', ['search' => $searchTerm]) }}"
               class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 shadow-2xs">
                <span>📊</span> Export Excel
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <form method="GET" action="{{ route('reports.batch-trace') }}" class="bg-white border border-neutral-200 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3 flex-1 max-w-xl">
            <div class="relative w-full">
                <input type="text" name="search" value="{{ $searchTerm ?? '' }}"
                       placeholder="Search by Batch # (e.g. BATCH-2026-001), Raw Lot #, or Product Name..."
                       class="w-full px-4 py-2 text-xs font-medium border border-neutral-300 rounded-xl focus:ring-2 focus:ring-neutral-900 focus:outline-hidden pl-9">
                <span class="absolute left-3 top-2.5 text-neutral-400 text-xs">🔍</span>
            </div>
            <button type="submit" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-xl transition-colors shadow-xs shrink-0">
                Trace Batch Chain
            </button>
            @if($searchTerm)
                <a href="{{ route('reports.batch-trace') }}" class="text-xs text-neutral-400 hover:text-neutral-700">Clear</a>
            @endif
        </div>

        <div class="text-xs text-neutral-500">
            Total Batches Traced: <span class="font-bold text-neutral-800">{{ $data['batches_count'] }}</span>
        </div>
    </form>

    <!-- Trace Timelines -->
    <div class="space-y-6">
        @forelse($data['traces'] as $item)
        <div class="bg-white border border-neutral-200 rounded-2xl shadow-xs overflow-hidden">
            <!-- Batch Header -->
            <div class="p-5 border-b border-neutral-100 bg-neutral-50/50 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 font-bold flex items-center justify-center text-sm border border-purple-200">
                        📦
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-neutral-900 font-mono">{{ $item['batch']->batch_number }}</h3>
                            @if($item['has_full_chain'])
                                <span class="px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-bold">
                                    ✓ Full Chain Traced
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md bg-neutral-100 text-neutral-600 text-[10px] font-bold">
                                    In Production / Partial
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-neutral-600 mt-0.5">
                            Product: <span class="font-bold text-neutral-800">{{ $item['product']?->product_name ?? 'Raw Lot Product' }}</span>
                            @if($item['product']?->sku)
                                <span class="text-neutral-400 font-mono">({{ $item['product']->sku }})</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-medium text-neutral-600">
                    <div>
                        <span class="text-neutral-400 text-[10px] block uppercase font-bold">Raw Material Lot</span>
                        <span class="font-mono font-bold text-neutral-800">{{ $item['raw_lot'] }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-400 text-[10px] block uppercase font-bold">Mfg Date</span>
                        <span>{{ \Carbon\Carbon::parse($item['batch']->production_date)->format('d M Y') }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-400 text-[10px] block uppercase font-bold">Batch Qty</span>
                        <span class="font-mono font-bold text-purple-700">{{ number_format((float) ($item['batch']->batch_qty ?? $item['batch']->quantity ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Trace Chain Stages (5 Steps Grid) -->
            <div class="p-5 grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
                <!-- Stage 1: Raw Material Lot -->
                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-neutral-700 border-b border-neutral-200/60 pb-1">
                        <span>1. Raw Material Lot</span>
                        <span class="text-neutral-400">🌾 Intake</span>
                    </div>
                    <div class="text-xs font-mono font-bold text-neutral-900">{{ $item['raw_lot'] }}</div>
                    <div class="text-[11px] text-neutral-500">Quality Verified Inward</div>
                </div>

                <!-- Stage 2: QC & Sample Trials -->
                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-neutral-700 border-b border-neutral-200/60 pb-1">
                        <span>2. QC & Samples ({{ $item['samples']->count() }})</span>
                        <span class="text-neutral-400">🧪 Testing</span>
                    </div>
                    @forelse($item['samples'] as $sample)
                        <div class="text-xs text-neutral-800">
                            <span class="font-bold">{{ $sample->sample_number ?? $sample->sample_code ?? 'Trial #'.$sample->id }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-bold ml-1">{{ $sample->trial_status ?? $sample->delivery_status ?? 'APPROVED' }}</span>
                            @if($sample->customer)
                                <div class="text-[10px] text-neutral-400 truncate">{{ $sample->customer->company_name }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-[11px] text-neutral-400 italic">No sample trials evaluated</div>
                    @endforelse
                </div>

                <!-- Stage 3: Commercial Dispatch (LR) -->
                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-neutral-700 border-b border-neutral-200/60 pb-1">
                        <span>3. Dispatches ({{ $item['shipments']->count() }})</span>
                        <span class="text-neutral-400">🚚 Transport</span>
                    </div>
                    @forelse($item['shipments'] as $shipment)
                        <div class="text-xs text-neutral-800">
                            <a href="{{ route('shipments.index') }}" class="font-mono font-bold text-neutral-900 hover:underline">
                                LR: {{ $shipment->lr_number }}
                            </a>
                            <div class="text-[10px] text-neutral-500">
                                {{ $shipment->transporter_name ?? 'Carrier' }} ({{ $shipment->status }})
                            </div>
                        </div>
                    @empty
                        <div class="text-[11px] text-neutral-400 italic">No dispatches recorded</div>
                    @endforelse
                </div>

                <!-- Stage 4: Tax Invoices & E-Way Bills -->
                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-neutral-700 border-b border-neutral-200/60 pb-1">
                        <span>4. Invoices & EWB ({{ $item['invoices']->count() }})</span>
                        <span class="text-neutral-400">🧾 Billing</span>
                    </div>
                    @forelse($item['invoices'] as $inv)
                        <div class="text-xs text-neutral-800">
                            <a href="{{ route('invoices.pdf', $inv->id) }}" target="_blank" class="font-bold text-neutral-900 hover:underline">
                                {{ $inv->invoice_number }}
                            </a>
                            <div class="text-[10px] text-neutral-500 truncate">{{ $inv->customer?->company_name }}</div>
                            @if($inv->commercialShipment?->ewayBill)
                                <div class="text-[10px] font-mono text-purple-700 font-bold">
                                    EWB: {{ $inv->commercialShipment->ewayBill->eway_bill_number }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-[11px] text-neutral-400 italic">No tax invoices generated</div>
                    @endforelse
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white border border-neutral-200 rounded-2xl p-12 text-center text-neutral-400">
            No production batches found matching the search criteria.
        </div>
        @endforelse
    </div>
</div>
@endsection
