<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ArAgingExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        return collect($this->reportData['invoices'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Invoice No.',
            'Invoice Date',
            'Due Date',
            'Customer Name',
            'Contact Phone',
            'Invoice Total (INR)',
            'Amount Received (INR)',
            'Balance Due (INR)',
            'Days Overdue',
            'Aging Bracket',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map(mixed $row): array
    {
        $bracket = match ($row['bucket']) {
            'CURRENT' => 'Current (0-30 Days)',
            'DAYS_0_30' => '1-30 Days Overdue',
            'DAYS_31_60' => '31-60 Days Overdue',
            'DAYS_61_90' => '61-90 Days Overdue',
            default => '90+ Days Critical',
        };

        return [
            $row['invoice_number'],
            $row['invoice_date'] ? Carbon::parse($row['invoice_date'])->format('Y-m-d') : '',
            $row['due_date'] ? Carbon::parse($row['due_date'])->format('Y-m-d') : '',
            $row['customer_name'],
            $row['customer_phone'] ?? '—',
            $row['total_amount'],
            $row['amount_received'],
            $row['balance_due'],
            $row['days_overdue'],
            $bracket,
        ];
    }

    public function title(): string
    {
        return 'AR Aging Report';
    }
}
