<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch & Serial Traceability Audit Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.3; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .header-box { border-bottom: 2px solid #091315; padding-bottom: 6px; margin-bottom: 10px; }
        .firm-title { font-size: 14px; font-weight: bold; color: #091315; }
        .report-title { font-size: 12px; font-weight: bold; }
        .batch-card { border: 1px solid #333; margin-bottom: 12px; page-break-inside: avoid; }
        .batch-header { background: #091315; color: #fff; padding: 5px 8px; font-weight: bold; }
        .stage-table { width: 100%; }
        .stage-col { width: 25%; vertical-align: top; border-right: 1px solid #ddd; padding: 6px; background: #fafafa; }
        .stage-col:last-child { border-right: none; }
        .stage-title { font-weight: bold; color: #091315; font-size: 9px; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header-box">
        <table>
            <tr>
                <td style="width: 60%;">
                    <div class="firm-title">{{ $firm?->firm_name ?? 'ENTERPRISE ERP' }}</div>
                    <div>{{ $firm?->address ?? '' }}</div>
                    @if($firm?->gst_number)
                        <div>GSTIN: {{ $firm->gst_number }}</div>
                    @endif
                </td>
                <td style="width: 40%; text-align: right;">
                    <div class="report-title uppercase">Batch Traceability & Chain Audit</div>
                    <div>Generated: {{ date('d-m-Y H:i') }}</div>
                    @if(!empty($searchTerm))
                        <div>Search: <strong>{{ $searchTerm }}</strong></div>
                    @endif
                    <div>Batches Traced: {{ $data['batches_count'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    @forelse($data['traces'] as $item)
    <div class="batch-card">
        <div class="batch-header">
            <table>
                <tr>
                    <td style="color: #fff; font-size: 10px;">
                        BATCH: <strong>{{ $item['batch']->batch_number }}</strong>
                        &nbsp;|&nbsp; Product: {{ $item['product']?->product_name ?? 'Raw Lot Item' }}
                        @if($item['product']?->sku) ({{ $item['product']->sku }}) @endif
                    </td>
                    <td class="text-right" style="color: #D7FF53; font-size: 9px;">
                        Mfg Date: {{ \Carbon\Carbon::parse($item['batch']->production_date)->format('d-m-Y') }}
                        &nbsp;|&nbsp; Batch Qty: {{ number_format((float) ($item['batch']->batch_qty ?? $item['batch']->quantity ?? 0), 2) }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="stage-table">
            <tr>
                <!-- 1. Raw Lot -->
                <td class="stage-col">
                    <div class="stage-title">1. RAW MATERIAL INTAKE</div>
                    <div>Lot #: <strong>{{ $item['raw_lot'] }}</strong></div>
                    <div style="color: #666; margin-top: 2px;">Quality Inspected Raw Supply</div>
                </td>

                <!-- 2. QC Samples -->
                <td class="stage-col">
                    <div class="stage-title">2. QC & SAMPLES ({{ $item['samples']->count() }})</div>
                    @forelse($item['samples'] as $sample)
                        <div style="margin-bottom: 3px;">
                            <strong>{{ $sample->sample_number ?? $sample->sample_code ?? 'Trial #'.$sample->id }}</strong>
                            <span style="color: #2563eb;">[{{ $sample->trial_status ?? $sample->delivery_status ?? 'APPROVED' }}]</span>
                            @if($sample->customer)
                                <div style="color: #666; font-size: 8px;">{{ $sample->customer->company_name }}</div>
                            @endif
                        </div>
                    @empty
                        <div style="color: #888; font-style: italic;">No samples linked</div>
                    @endforelse
                </td>

                <!-- 3. Dispatches -->
                <td class="stage-col">
                    <div class="stage-title">3. DISPATCHES ({{ $item['shipments']->count() }})</div>
                    @forelse($item['shipments'] as $shipment)
                        <div style="margin-bottom: 3px;">
                            LR: <strong>{{ $shipment->lr_number }}</strong>
                            <div style="color: #666; font-size: 8px;">Carrier: {{ $shipment->transporter_name ?? 'Direct' }}</div>
                        </div>
                    @empty
                        <div style="color: #888; font-style: italic;">No dispatches linked</div>
                    @endforelse
                </td>

                <!-- 4. Invoices & EWB -->
                <td class="stage-col">
                    <div class="stage-title">4. INVOICES & EWB ({{ $item['invoices']->count() }})</div>
                    @forelse($item['invoices'] as $inv)
                        <div style="margin-bottom: 3px;">
                            Inv: <strong>{{ $inv->invoice_number }}</strong>
                            <div style="color: #666; font-size: 8px;">{{ $inv->customer?->company_name }}</div>
                            @if($inv->commercialShipment?->ewayBill)
                                <div style="color: #7e22ce; font-size: 8px;">EWB: {{ $inv->commercialShipment->ewayBill->eway_bill_number }}</div>
                            @endif
                        </div>
                    @empty
                        <div style="color: #888; font-style: italic;">No tax invoices linked</div>
                    @endforelse
                </td>
            </tr>
        </table>
    </div>
    @empty
    <div style="text-align: center; padding: 30px; color: #888;">No batches matching the criteria.</div>
    @endforelse
</body>
</html>
