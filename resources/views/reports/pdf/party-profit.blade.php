<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Party Profitability Ranking</title>
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
                    <div class="report-title uppercase">Customer Profitability Ranking</div>
                    <div>Generated: {{ date('d-m-Y H:i') }}</div>
                    <div>Accounts: {{ $data['summary']['total_parties'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL ACCOUNTS</div>
                <div style="font-size: 11px; font-weight: bold;">{{ $data['summary']['total_parties'] }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL REVENUE</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['total_revenue'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL PROFIT</div>
                <div style="font-size: 11px; font-weight: bold; color: #15803d;">₹{{ number_format($data['summary']['total_profit'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOP ACCOUNT</div>
                <div style="font-size: 10px; font-weight: bold;">{{ $data['summary']['top_party']['company_name'] ?? 'N/A' }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">AVG PROFIT / PARTY</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['avg_party_profit'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">#</th>
                <th>Customer / Enterprise</th>
                <th>State & GSTIN</th>
                <th class="text-center">Invoices</th>
                <th class="text-right">Revenue (₹)</th>
                <th class="text-right">Profit (₹)</th>
                <th class="text-center">Margin %</th>
                <th class="text-center">Profit Share %</th>
                <th class="text-right">AR Outstanding (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['parties'] as $idx => $p)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="font-bold">{{ $p['company_name'] }}</td>
                <td>{{ $p['state'] }} ({{ $p['gst_number'] }})</td>
                <td class="text-center">{{ $p['invoice_count'] }}</td>
                <td class="text-right font-bold">{{ number_format($p['total_revenue'], 2) }}</td>
                <td class="text-right font-bold" style="color: {{ $p['gross_profit'] >= 0 ? '#15803d' : '#b91c1c' }};">
                    {{ number_format($p['gross_profit'], 2) }}
                </td>
                <td class="text-center">{{ $p['margin_percentage'] }}%</td>
                <td class="text-center font-bold">{{ $p['profit_contribution'] }}%</td>
                <td class="text-right">{{ number_format($p['total_outstanding'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px; color: #888;">No customer accounts found.</td>
            </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4" class="text-right uppercase">Total</td>
                <td class="text-right">{{ number_format($data['summary']['total_revenue'], 2) }}</td>
                <td class="text-right" style="color: #15803d;">{{ number_format($data['summary']['total_profit'], 2) }}</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
