<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'customers',
            'products',
            'product_categories',
            'sales_orders',
            'sales_order_items',
            'commercial_shipments',
            'commercial_shipment_items',
            'invoices',
            'invoice_items',
            'payment_receipts',
            'quotations',
            'quotation_items',
            'samples',
            'leads',
            'followup_tasks',
            'inventory_stocks',
            'production_batches',
            'activity_logs',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && ! Schema::hasColumn($tbl, 'tenant_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id');
                    $table->index('tenant_id');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'customers',
            'products',
            'product_categories',
            'sales_orders',
            'sales_order_items',
            'commercial_shipments',
            'commercial_shipment_items',
            'invoices',
            'invoice_items',
            'payment_receipts',
            'quotations',
            'quotation_items',
            'samples',
            'leads',
            'followup_tasks',
            'inventory_stocks',
            'production_batches',
            'activity_logs',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'tenant_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
