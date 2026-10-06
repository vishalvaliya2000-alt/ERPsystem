<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Enumerable
    {
        return Invoice::with('customer')->get();
    }

    public function headings(): array
    {
        return [
            'Invoice No.',
            'Invoice Date',
            'Customer',
            'Due Date',
            'Invoice Amount',
            'Amount Received',
            'Balance Due',
            'Status',
        ];
    }

    public function map(mixed $invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '',
            $invoice->customer->company_name ?? '',
            $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
            (float) $invoice->total_amount,
            (float) $invoice->amount_received,
            (float) $invoice->balance_due,
            $invoice->status,
        ];
    }

    public function title(): string
    {
        return 'Invoices';
    }
}
