<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomersExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Enumerable
    {
        return Customer::all();
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Company Name',
            'GSTIN',
            'Contact Person',
            'Phone',
            'Email',
            'City',
            'State',
            'Payment Terms',
            'Revenue',
            'Outstanding',
        ];
    }

    public function map(mixed $customer): array
    {
        return [
            $customer->customer_code,
            $customer->company_name,
            $customer->gst_number ?? '',
            $customer->primary_contact_person ?? '',
            $customer->primary_phone ?? '',
            $customer->primary_email ?? '',
            $customer->city ?? '',
            $customer->state ?? '',
            $customer->payment_terms_days.' Days',
            (float) $customer->total_revenue,
            (float) $customer->outstanding_amount,
        ];
    }

    public function title(): string
    {
        return 'Customers';
    }
}
