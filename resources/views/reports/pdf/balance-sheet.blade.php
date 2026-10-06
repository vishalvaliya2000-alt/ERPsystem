<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet - {{ \Carbon\Carbon::parse($asOfDate)->format('d-m-Y') }}</title>
    <style>
        @page { margin: 25px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.3; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .header-box { border-bottom: 2px solid #091315; padding-bottom: 8px; margin-bottom: 12px; }
        .firm-title { font-size: 16px; font-weight: bold; color: #091315; }
        .report-title { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .statement-table { width: 100%; margin-top: 8px; }
        .statement-col { width: 50%; vertical-align: top; border: 1px solid #333; padding: 0; }
        .col-header { background: #091315; color: #fff; font-weight: bold; padding: 6px 8px; font-size: 11px; }
        .section-header { background: #f0f0f0; font-weight: bold; padding: 4px 8px; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd; }
        .item-row td { padding: 3px 8px; }
        .subitem-row td { padding: 2px 8px 2px 18px; color: #555; font-size: 9px; }
        .total-row td { font-weight: bold; padding: 6px 8px; border-top: 2px solid #091315; background: #fafafa; }
        .footer-note { margin-top: 15px; font-size: 9px; color: #666; }
        .signatory-box { margin-top: 25px; text-align: right; }
    </style>
</head>
<body>
    <div class="header-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 65%;">
                    <div class="firm-title">{{ $firm?->firm_name ?? 'ENTERPRISE ERP' }}</div>
                    <div>{{ $firm?->address ?? '' }}</div>
                    @if($firm?->gst_number)
                        <div><strong>GSTIN:</strong> {{ $firm->gst_number }}</div>
                    @endif
                    @if($firm?->pan_number)
                        <div><strong>PAN:</strong> {{ $firm->pan_number }}</div>
                    @endif
                </td>
                <td style="width: 35%; text-align: right;">
                    <div class="report-title uppercase">Balance Sheet</div>
                    <div style="font-size: 10px; margin-top: 2px;">As of: <strong>{{ \Carbon\Carbon::parse($asOfDate)->format('d F Y') }}</strong></div>
                    <div style="font-size: 9px; color: #666;">Generated: {{ date('d-m-Y H:i') }}</div>
                    @if($data['is_balanced'])
                        <div style="margin-top: 4px; color: #15803d; font-weight: bold;">[ Balanced: A = L + E ]</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="statement-table">
        <tr>
            <!-- ASSETS -->
            <td class="statement-col" style="border-right: 1px solid #333;">
                <div class="col-header">ASSETS (Application of Funds)</div>
                <table>
                    <tr class="section-header">
                        <td>1. CURRENT ASSETS</td>
                        <td class="text-right">Amount (INR)</td>
                    </tr>
                    <tr class="item-row">
                        <td>Bank & Cash Balances</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['bank_and_cash_total'], 2) }}</td>
                    </tr>
                    @foreach($data['assets']['current']['bank_and_cash'] as $acc)
                    <tr class="subitem-row">
                        <td>{{ $acc->account_code }} - {{ $acc->account_name }}</td>
                        <td class="text-right">{{ number_format($acc->current_balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="item-row">
                        <td>Sundry Debtors (Trade Receivables)</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['sundry_debtors_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>GST Input Tax Credit (ITC)</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['gst_input_itc_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>Inventory & Stock on Hand</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['inventory_total'], 2) }}</td>
                    </tr>
                    @if($data['assets']['current']['other_current_total'] > 0)
                    <tr class="item-row">
                        <td>Other Current Assets</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['other_current_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="item-row" style="border-top: 1px solid #ddd; background: #f9f9f9;">
                        <td class="font-bold">Total Current Assets</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['current']['total'], 2) }}</td>
                    </tr>

                    <tr class="section-header">
                        <td>2. FIXED & NON-CURRENT ASSETS</td>
                        <td class="text-right">Amount (INR)</td>
                    </tr>
                    <tr class="item-row">
                        <td>Gross Tangible Fixed Assets</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['fixed']['tangible_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row" style="color: #b91c1c;">
                        <td>Less: Accumulated Depreciation</td>
                        <td class="text-right font-bold">-{{ number_format($data['assets']['fixed']['accumulated_depreciation_total'], 2) }}</td>
                    </tr>
                    @if($data['assets']['fixed']['other_total'] > 0)
                    <tr class="item-row">
                        <td>Other Non-Current Assets</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['fixed']['other_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="item-row" style="border-top: 1px solid #ddd; background: #f9f9f9;">
                        <td class="font-bold">Net Fixed Assets</td>
                        <td class="text-right font-bold">{{ number_format($data['assets']['fixed']['net_total'], 2) }}</td>
                    </tr>

                    <tr class="total-row">
                        <td class="uppercase">TOTAL ASSETS</td>
                        <td class="text-right">{{ number_format($data['assets']['grand_total'], 2) }}</td>
                    </tr>
                </table>
            </td>

            <!-- LIABILITIES & EQUITY -->
            <td class="statement-col">
                <div class="col-header">LIABILITIES & EQUITY (Sources of Funds)</div>
                <table>
                    <tr class="section-header">
                        <td>1. CURRENT LIABILITIES</td>
                        <td class="text-right">Amount (INR)</td>
                    </tr>
                    <tr class="item-row">
                        <td>Sundry Creditors (Trade Payables)</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['current']['sundry_creditors_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>Output GST Duties & Taxes Payable</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['current']['output_gst_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>TCS / TDS Withholding Payable</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['current']['tcs_tds_total'], 2) }}</td>
                    </tr>
                    @if($data['liabilities']['current']['other_current_total'] > 0)
                    <tr class="item-row">
                        <td>Other Current Liabilities</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['current']['other_current_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="item-row" style="border-top: 1px solid #ddd; background: #f9f9f9;">
                        <td class="font-bold">Total Current Liabilities</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['current']['total'], 2) }}</td>
                    </tr>

                    <tr class="section-header">
                        <td>2. NON-CURRENT LIABILITIES</td>
                        <td class="text-right">Amount (INR)</td>
                    </tr>
                    <tr class="item-row">
                        <td>Long Term Debt & Borrowings</td>
                        <td class="text-right font-bold">{{ number_format($data['liabilities']['non_current']['total'], 2) }}</td>
                    </tr>

                    <tr class="section-header">
                        <td>3. SHAREHOLDERS EQUITY & CAPITAL</td>
                        <td class="text-right">Amount (INR)</td>
                    </tr>
                    <tr class="item-row">
                        <td>Owner Capital / Share Capital</td>
                        <td class="text-right font-bold">{{ number_format($data['equity']['share_capital_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>Retained Reserves & Surplus</td>
                        <td class="text-right font-bold">{{ number_format($data['equity']['reserves_total'], 2) }}</td>
                    </tr>
                    <tr class="item-row">
                        <td>Current Period Net Profit / (Loss)</td>
                        <td class="text-right font-bold">{{ number_format($data['equity']['net_profit'], 2) }}</td>
                    </tr>
                    <tr class="item-row" style="border-top: 1px solid #ddd; background: #f9f9f9;">
                        <td class="font-bold">Total Equity & Reserves</td>
                        <td class="text-right font-bold">{{ number_format($data['equity']['total'], 2) }}</td>
                    </tr>

                    <tr class="total-row">
                        <td class="uppercase">TOTAL LIABILITIES & EQUITY</td>
                        <td class="text-right">{{ number_format($data['total_liabilities_and_equity'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 25px;">
        <tr>
            <td style="width: 60%; vertical-align: top;" class="footer-note">
                <div>Statutory verification: Assets = Total Liabilities + Equity Reserves.</div>
                <div>This financial statement was generated electronically from ERP general ledger postings.</div>
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div class="font-bold">For {{ $firm?->firm_name ?? 'Enterprise ERP' }}</div>
                <div style="margin-top: 40px; border-top: 1px solid #333; display: inline-block; padding-top: 4px;">
                    Authorized Signatory / Accountant
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
