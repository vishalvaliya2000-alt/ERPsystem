<?php

use App\Exports\CustomersExport;
use App\Exports\InvoicesExport;
use App\Exports\MasterWorkbookExport;
use App\Exports\ProductsExport;
use App\Exports\SalesOrdersExport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

test('authenticated user can view excel sync management center', function () {
    $user = User::first() ?? User::factory()->create();

    $response = $this->actingAs($user)->get(route('excel.index'));

    $response->assertStatus(200);
    $response->assertSee('Excel Reports & Export');
    $response->assertSee('Download Multi-Sheet Excel');
});

test('authenticated user can trigger excel workbook download', function () {
    $user = User::first() ?? User::factory()->create();

    Excel::fake();

    $response = $this->actingAs($user)->get(route('excel.export'));

    $response->assertStatus(200);

    Excel::assertDownloaded('ERP_Master_Export_'.date('Y-m-d').'.xlsx', function (MasterWorkbookExport $export) {
        $sheets = $export->sheets();
        expect($sheets)->toHaveCount(4);
        expect($sheets[0])->toBeInstanceOf(CustomersExport::class);
        expect($sheets[1])->toBeInstanceOf(ProductsExport::class);
        expect($sheets[2])->toBeInstanceOf(SalesOrdersExport::class);
        expect($sheets[3])->toBeInstanceOf(InvoicesExport::class);

        return true;
    });
});

test('export classes define correct titles and headings', function () {
    $customersExport = new CustomersExport;
    expect($customersExport->title())->toBe('Customers');
    expect($customersExport->headings())->toContain('Customer Code', 'Company Name', 'GSTIN');

    $productsExport = new ProductsExport;
    expect($productsExport->title())->toBe('Products');
    expect($productsExport->headings())->toContain('Product Code', 'Product Name', 'Category');

    $ordersExport = new SalesOrdersExport;
    expect($ordersExport->title())->toBe('Sales Orders');
    expect($ordersExport->headings())->toContain('Order No.', 'Order Date', 'Customer');

    $invoicesExport = new InvoicesExport;
    expect($invoicesExport->title())->toBe('Invoices');
    expect($invoicesExport->headings())->toContain('Invoice No.', 'Invoice Date', 'Customer');
});
