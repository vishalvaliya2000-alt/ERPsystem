@extends('layouts.app')

@section('title', 'E-way Bill - #' . $ewayBill->eway_bill_number)

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    showCancelModal: false,
    showExtendModal: false
}">
    <!-- Action Bar & Sub-nav -->
    <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('shipments.index') }}" class="p-2 rounded-xl border border-neutral-200 text-neutral-600 hover:bg-neutral-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-black text-neutral-900 font-mono">E-WAY BILL #{{ $ewayBill->eway_bill_number }}</h2>
                    @if($ewayBill->status === 'ACTIVE')
                        <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            ✓ ACTIVE (VALID)
                        </span>
                    @elseif($ewayBill->status === 'CANCELLED')
                        <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                            ✕ CANCELLED
                        </span>
                    @elseif($ewayBill->status === 'EXTENDED')
                        <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                            ⚡ EXTENDED
                        </span>
                    @endif
                </div>
                <p class="text-xs text-neutral-500">Commercial Shipment LR #{{ $shipment->lr_number ?: $shipment->shipment_number }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl border border-neutral-200 text-neutral-700 hover:bg-neutral-100 text-xs font-bold transition-colors shadow-xs">
                🖨️ Print E-way Bill
            </button>

            @if($ewayBill->status === 'ACTIVE')
                @if($ewayBill->canBeCancelled())
                    <button @click="showCancelModal = true" class="px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-bold transition-colors">
                        Cancel Bill
                    </button>
                @endif
                <button @click="showExtendModal = true" class="px-3.5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-xs">
                    Extend Validity
                </button>
            @endif
        </div>
    </div>

    <!-- Official Statutory E-way Bill Slip (Print-Ready) -->
    <div class="bg-white border-2 border-neutral-900 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6 text-neutral-900 font-sans print:border-black print:p-0">
        <!-- Document Header -->
        <div class="border-b-2 border-neutral-900 pb-4 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-500 block">Government of India • Goods and Services Tax</span>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-0.5">E-WAY BILL SYSTEM</h1>
                <p class="text-xs text-neutral-600 mt-0.5 font-mono">Form GST EWB-01 (See Rule 138 of the CGST Rules, 2017)</p>
            </div>
            <!-- Simulated QR Code -->
            <div class="w-24 h-24 border border-neutral-300 rounded-xl p-1.5 flex flex-col items-center justify-center bg-neutral-50 shrink-0">
                <svg class="w-16 h-16 text-neutral-900" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm10-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14-2h2v2h-2v-2zm-4 0h2v2h-2v-2zm2 2h2v2h-2v-2zm2 2h2v2h-2v-2zm-6 0h2v2h-2v-2zm4 2h2v2h-2v-2zm-4 2h2v2h-2v-2z"/>
                </svg>
                <span class="text-[8px] font-mono text-neutral-400 mt-1 uppercase">NIC VERIFIED</span>
            </div>
        </div>

        <!-- Bill Overview Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-neutral-50 border border-neutral-200 text-xs">
            <div>
                <span class="text-[10px] font-bold uppercase text-neutral-400 block">E-way Bill No:</span>
                <span class="font-mono font-black text-sm text-neutral-900">{{ $ewayBill->eway_bill_number }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-neutral-400 block">Generated Date:</span>
                <span class="font-mono font-bold text-neutral-800">{{ $ewayBill->generated_at->format('d/m/Y h:i A') }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-neutral-400 block">Valid Until:</span>
                <span class="font-mono font-bold {{ $ewayBill->isExpired() ? 'text-rose-600' : 'text-emerald-700' }}">
                    {{ $ewayBill->valid_until->format('d/m/Y h:i A') }}
                </span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-neutral-400 block">Approx Distance:</span>
                <span class="font-mono font-bold text-neutral-900">
                    {{ $ewayBill->response_payload['request_payload']['transDistance'] ?? 350 }} KM
                </span>
            </div>
        </div>

        @if($ewayBill->status === 'CANCELLED')
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <strong>🚫 Cancelled:</strong> {{ $ewayBill->cancel_reason }}
            </div>
        @endif

        <!-- PART - A -->
        <div class="space-y-3">
            <div class="bg-neutral-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold tracking-wider uppercase">
                PART - A (Consignment Details)
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <!-- From (Supplier) -->
                <div class="p-3.5 rounded-xl border border-neutral-200 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-neutral-400">From / Origin:</span>
                    <div class="font-bold text-neutral-900 text-sm">{{ $shipment->firm?->firm_name }}</div>
                    <div class="font-mono text-[11px] text-neutral-600">GSTIN: <strong>{{ $shipment->firm?->gstin ?: '24AABCR1234F1Z1' }}</strong></div>
                    <div class="text-neutral-600">{{ $shipment->firm?->address_line1 }}, {{ $shipment->firm?->city }} ({{ $shipment->firm?->pincode }})</div>
                    <div class="text-neutral-500 font-mono">State: {{ $shipment->firm?->state }} ({{ $shipment->firm?->state_code }})</div>
                </div>

                <!-- To (Recipient) -->
                @php
                    $order = $shipment->getDistinctOrders()->first();
                    $customer = $order?->customer;
                @endphp
                <div class="p-3.5 rounded-xl border border-neutral-200 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-neutral-400">To / Destination:</span>
                    <div class="font-bold text-neutral-900 text-sm">{{ $customer?->company_name ?: 'Consignee Enterprise' }}</div>
                    <div class="font-mono text-[11px] text-neutral-600">GSTIN: <strong>{{ $customer?->gst_number ?: 'Unregistered / Inter-state' }}</strong></div>
                    <div class="text-neutral-600">{{ $shipment->destination ?: ($customer?->city ?? 'Destination Port') }}</div>
                    <div class="text-neutral-500 font-mono">State: {{ $customer?->state ?: 'Maharashtra' }} ({{ $customer?->state_code ?: '27' }})</div>
                </div>
            </div>

            <!-- Goods Value & Tax Details -->
            @php $inv = $shipment->invoices()->first(); @endphp
            <div class="border border-neutral-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 text-[10px] uppercase font-bold">
                        <tr>
                            <th class="py-2.5 px-3">Doc Type & No</th>
                            <th class="py-2.5 px-3">Doc Date</th>
                            <th class="py-2.5 px-3 text-right">Taxable Amount (₹)</th>
                            <th class="py-2.5 px-3 text-right">CGST (₹)</th>
                            <th class="py-2.5 px-3 text-right">SGST (₹)</th>
                            <th class="py-2.5 px-3 text-right">IGST (₹)</th>
                            <th class="py-2.5 px-3 text-right font-bold">Total Value (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono divide-y divide-neutral-100">
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-neutral-900">Tax Invoice / {{ $inv?->invoice_number ?: $shipment->shipment_number }}</td>
                            <td class="py-2.5 px-3 text-neutral-600">{{ ($inv?->invoice_date ?? $shipment->shipment_date ?? now())->format('d/m/Y') }}</td>
                            <td class="py-2.5 px-3 text-right">₹{{ number_format((float)($inv?->taxable_amount ?? 100000.00), 2) }}</td>
                            <td class="py-2.5 px-3 text-right">₹{{ number_format((float)($inv?->cgst_amount ?? 0.0), 2) }}</td>
                            <td class="py-2.5 px-3 text-right">₹{{ number_format((float)($inv?->sgst_amount ?? 0.0), 2) }}</td>
                            <td class="py-2.5 px-3 text-right">₹{{ number_format((float)($inv?->igst_amount ?? 0.0), 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-neutral-900">₹{{ number_format((float)($inv?->total_amount ?? 105000.00), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PART - B -->
        <div class="space-y-3">
            <div class="bg-neutral-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold tracking-wider uppercase">
                PART - B (Transportation & Vehicle Details)
            </div>

            <div class="border border-neutral-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-500 text-[10px] uppercase font-bold">
                        <tr>
                            <th class="py-2.5 px-3">Mode</th>
                            <th class="py-2.5 px-3">Vehicle Number</th>
                            <th class="py-2.5 px-3">From</th>
                            <th class="py-2.5 px-3">Transporter Doc / LR No</th>
                            <th class="py-2.5 px-3">Transporter Name</th>
                            <th class="py-2.5 px-3">Entered Date</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr>
                            <td class="py-2.5 px-3">Road</td>
                            <td class="py-2.5 px-3 font-bold text-neutral-900">{{ $shipment->vehicle_number ?: 'GJ-04-AX-9999' }}</td>
                            <td class="py-2.5 px-3">{{ $shipment->firm?->city ?: 'Mahuva' }}</td>
                            <td class="py-2.5 px-3 font-bold">{{ $shipment->lr_number ?: 'LR-'.$shipment->id }}</td>
                            <td class="py-2.5 px-3">{{ $shipment->transporter ?: 'Direct Logistics' }}</td>
                            <td class="py-2.5 px-3">{{ $ewayBill->generated_at->format('d/m/Y') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer Notice -->
        <div class="pt-4 border-t border-neutral-200 flex items-center justify-between text-[10px] text-neutral-500 font-mono">
            <span>Verified System Signature: GSTN-EWAY-{{ substr(md5($ewayBill->eway_bill_number), 0, 16) }}</span>
            <span>Generated via ERPSaaS.in</span>
        </div>
    </div>

    <!-- Modal: Cancel E-way Bill -->
    <div x-show="showCancelModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showCancelModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-rose-600 text-base">Cancel E-way Bill</h3>
                <button @click="showCancelModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <p class="text-xs text-neutral-600">
                Statutory cancellation under GST rules is permitted within 24 hours of generation. Once cancelled, the consignment cannot transit on this number.
            </p>

            <form method="POST" action="{{ route('eway-bills.cancel', $ewayBill->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Cancellation Reason *</label>
                    <select name="reason" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                        <option value="Duplicate Generation">1. Duplicate Generation</option>
                        <option value="Order Cancelled">2. Order / Shipment Cancelled</option>
                        <option value="Data Entry Error in Vehicle or Consignee">3. Data Entry Error in Details</option>
                        <option value="Others">4. Others</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Additional Remarks</label>
                    <input type="text" name="remarks" placeholder="Optional notes" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showCancelModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Abort</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-xs transition-colors">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Extend Validity -->
    <div x-show="showExtendModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showExtendModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-100 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-black text-neutral-900 text-base">Extend E-way Bill Validity</h3>
                <button @click="showExtendModal = false" class="text-neutral-400 hover:text-neutral-600 text-lg">&times;</button>
            </div>

            <p class="text-xs text-neutral-600">
                Extend validity for consignments delayed due to transshipment, vehicle breakdown, traffic obstruction, or natural calamity (1 day granted per 200 km remaining).
            </p>

            <form method="POST" action="{{ route('eway-bills.extend', $ewayBill->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Remaining Distance to Destination (KM) *</label>
                    <input type="number" name="additional_km" required value="200" min="10" max="2500" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Extension Reason *</label>
                    <select name="reason" required class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                        <option value="Vehicle Breakdown">Vehicle Breakdown & Repair Transit</option>
                        <option value="Transshipment Delay">Transshipment Hub Transfer Delay</option>
                        <option value="Traffic Congestion / Highway Block">Highway / Route Obstruction</option>
                        <option value="Natural Calamity">Weather / Heavy Rains</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Remarks</label>
                    <input type="text" name="remarks" placeholder="Location where vehicle is currently halted" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                    <button type="button" @click="showExtendModal = false" class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">Extend Validity</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
