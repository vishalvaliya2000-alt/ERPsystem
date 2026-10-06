<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Enumerable
    {
        return Product::with('category')->get();
    }

    public function headings(): array
    {
        return [
            'Product Code',
            'Product Name',
            'Category',
            'UOM',
            'Standard Rate',
            'HSN Code',
            'Active',
        ];
    }

    public function map(mixed $product): array
    {
        return [
            $product->product_code,
            $product->product_name,
            $product->category->name ?? 'Dehydrated',
            $product->uom,
            (float) $product->standard_rate,
            $product->hsn_code,
            $product->is_active ? 'Yes' : 'No',
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
