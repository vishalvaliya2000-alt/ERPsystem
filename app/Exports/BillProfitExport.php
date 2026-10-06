<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class BillProfitExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        return collect($this->reportData['rows'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Invoice No.',
            'Invoice Date',
            'Customer Name',
            'Consignment / LR',
            'E-Way Bill No.',
            'Total Billed (INR)',
            'Revenue (INR)',
            'Total Cost (INR)',
            'Gross Profit (INR)',
            'Margin %',
            'Profit Health',
            'Status',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map(mixed $row): array
    {
        return [
            $row['invoice_number'],
            $row['invoice_date'] ? Carbon::parse($row['invoice_date'])->format('Y-m-d') : '',
            $row['customer_name'],
            $row['lr_number'],
            $row['eway_bill_number'] ?? '—',
            $row['total_amount'],
            $row['revenue'],
            $row['cost'],
            $row['gross_profit'],
            $row['margin_percentage'].'%',
            $row['health'],
            $row['status'],
        ];
    }

    public function title(): string
    {
        return 'Bill-Wise Gross Profit';
    }
}
