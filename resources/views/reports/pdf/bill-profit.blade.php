<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill-Wise Profit Analysis</title>
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
        .kpi-table { margin-bottom: 10px; }
        .kpi-box { border: 1px solid #ddd; background: #f9f9f9; padding: 4px 6px; text-align: center; }
        .data-table { border: 1px solid #333; }
        .data-table th { background: #091315; color: #fff; font-size: 9px; font-weight: bold; padding: 5px 6px; border: 1px solid #333; }
        .data-table td { border: 1px solid #ddd; padding: 4px 6px; }
        .total-row td { background: #f0f0f0; font-weight: bold; border-top: 2px solid #333; }
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
                    <div class="report-title uppercase">Bill-Wise Profit & Margin Report</div>
                    <div>Generated: {{ date('d-m-Y H:i') }}</div>
                    <div>Invoices: {{ $data['summary']['total_invoices'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- KPI Summary -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL BILLED</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['total_billed'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">NET REVENUE</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['total_revenue'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL COGS</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['total_cost'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">GROSS PROFIT</div>
                <div style="font-size: 11px; font-weight: bold; color: #15803d;">₹{{ number_format($data['summary']['total_profit'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">AVERAGE MARGIN</div>
                <div style="font-size: 11px; font-weight: bold;">{{ $data['summary']['avg_margin'] }}%</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice No.</th>
                <th>Date</th>
                <th>Customer</th>
                <th>LR / Consignment</th>
                <th class="text-right">Revenue (₹)</th>
                <th class="text-right">Cost (COGS) (₹)</th>
                <th class="text-right">Profit (₹)</th>
                <th class="text-center">Margin %</th>
                <th class="text-center">Health</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
            <tr>
                <td class="font-bold">{{ $row['invoice_number'] }}</td>
                <td>{{ \Carbon\Carbon::parse($row['invoice_date'])->format('d-m-Y') }}</td>
                <td>{{ $row['customer_name'] }}</td>
                <td>{{ $row['lr_number'] }}</td>
                <td class="text-right font-bold">{{ number_format($row['revenue'], 2) }}</td>
                <td class="text-right">{{ number_format($row['cost'], 2) }}</td>
                <td class="text-right font-bold" style="color: {{ $row['gross_profit'] >= 0 ? '#15803d' : '#b91c1c' }};">
                    {{ number_format($row['gross_profit'], 2) }}
                </td>
                <td class="text-center font-bold">{{ $row['margin_percentage'] }}%</td>
                <td class="text-center">{{ $row['health'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px; color: #888;">No invoices match the specified criteria.</td>
            </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4" class="text-right uppercase">Total</td>
                <td class="text-right">{{ number_format($data['summary']['total_revenue'], 2) }}</td>
                <td class="text-right">{{ number_format($data['summary']['total_cost'], 2) }}</td>
                <td class="text-right" style="color: #15803d;">{{ number_format($data['summary']['total_profit'], 2) }}</td>
                <td class="text-center">{{ $data['summary']['avg_margin'] }}%</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
