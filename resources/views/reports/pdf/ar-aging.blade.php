<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts Receivable Aging Schedule</title>
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
                    <div class="report-title uppercase">Accounts Receivable Aging Schedule</div>
                    <div>As of Date: {{ \Carbon\Carbon::parse($data['as_of_date'])->format('d F Y') }}</div>
                    <div>Debtors: {{ count($data['customers']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">TOTAL OUTSTANDING</div>
                <div style="font-size: 11px; font-weight: bold;">₹{{ number_format($data['summary']['total_outstanding'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">CURRENT</div>
                <div style="font-size: 11px; font-weight: bold; color: #15803d;">₹{{ number_format($data['summary']['current_total'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">1–30 DAYS</div>
                <div style="font-size: 11px; font-weight: bold; color: #2563eb;">₹{{ number_format($data['summary']['days_0_30_total'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">31–60 DAYS</div>
                <div style="font-size: 11px; font-weight: bold; color: #d97706;">₹{{ number_format($data['summary']['days_31_60_total'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">61–90 DAYS</div>
                <div style="font-size: 11px; font-weight: bold; color: #ea580c;">₹{{ number_format($data['summary']['days_61_90_total'], 2) }}</div>
            </td>
            <td class="kpi-box">
                <div style="color: #666; font-size: 8px;">90+ DAYS</div>
                <div style="font-size: 11px; font-weight: bold; color: #b91c1c;">₹{{ number_format($data['summary']['days_90_plus_total'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Customer / Debtor Account</th>
                <th class="text-right">Total Balance (₹)</th>
                <th class="text-right">Current (₹)</th>
                <th class="text-right">1–30 Days (₹)</th>
                <th class="text-right">31–60 Days (₹)</th>
                <th class="text-right">61–90 Days (₹)</th>
                <th class="text-right">90+ Days (₹)</th>
                <th class="text-center">Invoices</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['customers'] as $cust)
            <tr>
                <td class="font-bold">
                    {{ $cust['customer_name'] }}
                    @if($cust['customer_phone'])
                        <span style="color: #666; font-size: 8px;">({{ $cust['customer_phone'] }})</span>
                    @endif
                </td>
                <td class="text-right font-bold">{{ number_format($cust['total_balance'], 2) }}</td>
                <td class="text-right">{{ $cust['current'] > 0 ? number_format($cust['current'], 2) : '—' }}</td>
                <td class="text-right">{{ $cust['days_0_30'] > 0 ? number_format($cust['days_0_30'], 2) : '—' }}</td>
                <td class="text-right">{{ $cust['days_31_60'] > 0 ? number_format($cust['days_31_60'], 2) : '—' }}</td>
                <td class="text-right">{{ $cust['days_61_90'] > 0 ? number_format($cust['days_61_90'], 2) : '—' }}</td>
                <td class="text-right" style="color: {{ $cust['days_90_plus'] > 0 ? '#b91c1c' : '#333' }}; font-weight: {{ $cust['days_90_plus'] > 0 ? 'bold' : 'normal' }};">
                    {{ $cust['days_90_plus'] > 0 ? number_format($cust['days_90_plus'], 2) : '—' }}
                </td>
                <td class="text-center">{{ $cust['invoices_count'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 15px; color: #888;">Zero accounts receivable balances pending.</td>
            </tr>
            @endforelse
            <tr class="total-row">
                <td class="uppercase">Total</td>
                <td class="text-right">{{ number_format($data['summary']['total_outstanding'], 2) }}</td>
                <td class="text-right">{{ number_format($data['summary']['current_total'], 2) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_0_30_total'], 2) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_31_60_total'], 2) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_61_90_total'], 2) }}</td>
                <td class="text-right" style="color: #b91c1c;">{{ number_format($data['summary']['days_90_plus_total'], 2) }}</td>
                <td class="text-center">{{ $data['summary']['invoices_count'] }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
