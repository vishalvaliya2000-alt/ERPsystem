<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PartyProfitExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        return collect($this->reportData['parties'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer Name',
            'State',
            'GSTIN',
            'Invoices Count',
            'Total Billed (INR)',
            'Revenue (INR)',
            'Total Cost (INR)',
            'Gross Profit (INR)',
            'Margin %',
            'Profit Contribution %',
            'Outstanding Balance (INR)',
        ];
    }

    /**
     * @param  array<string, mixed>  $party
     */
    public function map(mixed $party): array
    {
        return [
            $party['customer_code'],
            $party['company_name'],
            $party['state'],
            $party['gst_number'],
            $party['invoice_count'],
            $party['total_billed'],
            $party['total_revenue'],
            $party['total_cost'],
            $party['gross_profit'],
            $party['margin_percentage'].'%',
            $party['profit_contribution'].'%',
            $party['total_outstanding'],
        ];
    }

    public function title(): string
    {
        return 'Party-Wise Profitability';
    }
}
