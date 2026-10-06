<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class BatchTraceExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected array $reportData
    ) {}

    public function collection(): Collection
    {
        return collect($this->reportData['traces'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Batch Number',
            'Product Name',
            'Raw Material Lot',
            'Production Date',
            'Batch Qty (KG)',
            'Available Qty (KG)',
            'Moisture %',
            'Sensory Grade',
            'QC Status',
            'Linked Shipments / LRs',
            'Linked Invoices',
        ];
    }

    /**
     * @param  array<string, mixed>  $trace
     */
    public function map(mixed $trace): array
    {
        $batch = $trace['batch'];
        $lrList = $trace['shipments']->pluck('lr_number')->filter()->join(', ');
        $invoiceList = $trace['invoices']->pluck('invoice_number')->filter()->join(', ');

        return [
            $batch->batch_number,
            $trace['product']?->product_name ?? 'Product',
            $trace['raw_lot'],
            $batch->production_date ? Carbon::parse($batch->production_date)->format('Y-m-d') : '',
            (float) $batch->batch_qty,
            (float) $batch->available_qty,
            (float) $batch->moisture_percentage.'%',
            $batch->sensory_grade ?? 'Standard',
            $batch->status,
            $lrList ?: 'No dispatches recorded',
            $invoiceList ?: 'No invoices recorded',
        ];
    }

    public function title(): string
    {
        return 'Batch Traceability';
    }
}
