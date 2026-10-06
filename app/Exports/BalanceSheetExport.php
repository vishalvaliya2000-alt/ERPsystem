<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BalanceSheetExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $balanceSheetData
     */
    public function __construct(
        protected array $balanceSheetData
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        // 1. ASSETS SECTION
        $rows->push(['=== ASSETS ===', '', '']);
        $rows->push(['1. CURRENT ASSETS', '', '']);
        $rows->push(['  Bank & Cash Balances', '', $this->balanceSheetData['assets']['current']['bank_and_cash_total']]);
        $rows->push(['  Sundry Debtors (Trade Receivables)', '', $this->balanceSheetData['assets']['current']['sundry_debtors_total']]);
        $rows->push(['  GST Input Tax Credit (ITC)', '', $this->balanceSheetData['assets']['current']['gst_input_itc_total']]);
        $rows->push(['  Inventory & Stock', '', $this->balanceSheetData['assets']['current']['inventory_total']]);
        $rows->push(['  Other Current Assets', '', $this->balanceSheetData['assets']['current']['other_current_total']]);
        $rows->push(['Total Current Assets', '', $this->balanceSheetData['assets']['current']['total']]);
        $rows->push(['', '', '']);

        $rows->push(['2. NON-CURRENT & FIXED ASSETS', '', '']);
        $rows->push(['  Gross Tangible Fixed Assets', '', $this->balanceSheetData['assets']['fixed']['tangible_total']]);
        $rows->push(['  Less: Accumulated Depreciation', '', -$this->balanceSheetData['assets']['fixed']['accumulated_depreciation_total']]);
        $rows->push(['  Other Non-Current Assets', '', $this->balanceSheetData['assets']['fixed']['other_total']]);
        $rows->push(['Net Fixed Assets', '', $this->balanceSheetData['assets']['fixed']['net_total']]);
        $rows->push(['TOTAL ASSETS', '', $this->balanceSheetData['assets']['grand_total']]);
        $rows->push(['', '', '']);

        // 2. LIABILITIES SECTION
        $rows->push(['=== LIABILITIES & EQUITY ===', '', '']);
        $rows->push(['1. CURRENT LIABILITIES', '', '']);
        $rows->push(['  Sundry Creditors (Trade Payables)', '', $this->balanceSheetData['liabilities']['current']['sundry_creditors_total']]);
        $rows->push(['  Output GST Duties & Taxes', '', $this->balanceSheetData['liabilities']['current']['output_gst_total']]);
        $rows->push(['  TCS / TDS Withholding Payable', '', $this->balanceSheetData['liabilities']['current']['tcs_tds_total']]);
        $rows->push(['  Other Current Liabilities', '', $this->balanceSheetData['liabilities']['current']['other_current_total']]);
        $rows->push(['Total Current Liabilities', '', $this->balanceSheetData['liabilities']['current']['total']]);
        $rows->push(['', '', '']);

        $rows->push(['2. NON-CURRENT LIABILITIES', '', '']);
        $rows->push(['  Term Loans & Long-Term Debt', '', $this->balanceSheetData['liabilities']['non_current']['total']]);
        $rows->push(['Total Liabilities', '', $this->balanceSheetData['liabilities']['total']]);
        $rows->push(['', '', '']);

        $rows->push(['3. SHAREHOLDERS EQUITY & CAPITAL', '', '']);
        $rows->push(['  Owner Capital / Share Capital', '', $this->balanceSheetData['equity']['share_capital_total']]);
        $rows->push(['  Reserves & Surplus', '', $this->balanceSheetData['equity']['reserves_total']]);
        $rows->push(['  Current Period Net Profit / (Loss)', '', $this->balanceSheetData['equity']['net_profit']]);
        $rows->push(['Total Equity', '', $this->balanceSheetData['equity']['total']]);
        $rows->push(['TOTAL LIABILITIES & EQUITY', '', $this->balanceSheetData['total_liabilities_and_equity']]);
        $rows->push(['Balance Invariant Difference', '', $this->balanceSheetData['difference']]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Account Head / Group',
            'Sub-Classification',
            'Amount (INR)',
        ];
    }

    public function title(): string
    {
        return 'Balance Sheet';
    }
}
