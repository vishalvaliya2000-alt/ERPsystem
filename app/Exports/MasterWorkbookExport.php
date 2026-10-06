<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterWorkbookExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new CustomersExport,
            new ProductsExport,
            new SalesOrdersExport,
            new InvoicesExport,
        ];
    }
}
