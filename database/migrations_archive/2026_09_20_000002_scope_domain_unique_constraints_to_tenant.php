<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Categories (name & slug scoped to tenant)
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('product_categories_name_unique');
            $table->dropUnique('product_categories_slug_unique');
            $table->unique(['tenant_id', 'name'], 'product_categories_tenant_id_name_unique');
            $table->unique(['tenant_id', 'slug'], 'product_categories_tenant_id_slug_unique');
        });

        // 2. Products Master (product_code / SKU scoped to tenant)
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_product_code_unique');
            $table->unique(['tenant_id', 'product_code'], 'products_tenant_id_product_code_unique');
        });

        // 3. Quotations (quotation_number scoped to tenant)
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique('quotations_quotation_number_unique');
            $table->unique(['tenant_id', 'quotation_number'], 'quotations_tenant_id_quotation_number_unique');
        });

        // 4. Sales Orders (order_number scoped to tenant)
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique('sales_orders_order_number_unique');
            $table->unique(['tenant_id', 'order_number'], 'sales_orders_tenant_id_order_number_unique');
        });

        // 5. Invoices (invoice_number scoped to tenant)
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_number_unique');
            $table->unique(['tenant_id', 'invoice_number'], 'invoices_tenant_id_invoice_number_unique');
        });

        // 6. Payment Receipts (receipt_number scoped to tenant)
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropUnique('payment_receipts_receipt_number_unique');
            $table->unique(['tenant_id', 'receipt_number'], 'payment_receipts_tenant_id_receipt_number_unique');
        });

        // 7. Commercial Shipments (shipment_number scoped to tenant)
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->dropUnique('commercial_shipments_shipment_number_unique');
            $table->unique(['tenant_id', 'shipment_number'], 'commercial_shipments_tenant_id_shipment_number_unique');
        });

        // 8. Production Batches (batch_number scoped to tenant)
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropUnique('production_batches_batch_number_unique');
            $table->unique(['tenant_id', 'batch_number'], 'production_batches_tenant_id_batch_number_unique');
        });

        // 9. Samples (sample_number scoped to tenant)
        Schema::table('samples', function (Blueprint $table) {
            $table->dropUnique('samples_sample_number_unique');
            $table->unique(['tenant_id', 'sample_number'], 'samples_tenant_id_sample_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropUnique('samples_tenant_id_sample_number_unique');
            $table->unique('sample_number', 'samples_sample_number_unique');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropUnique('production_batches_tenant_id_batch_number_unique');
            $table->unique('batch_number', 'production_batches_batch_number_unique');
        });

        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->dropUnique('commercial_shipments_tenant_id_shipment_number_unique');
            $table->unique('shipment_number', 'commercial_shipments_shipment_number_unique');
        });

        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropUnique('payment_receipts_tenant_id_receipt_number_unique');
            $table->unique('receipt_number', 'payment_receipts_receipt_number_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_id_invoice_number_unique');
            $table->unique('invoice_number', 'invoices_invoice_number_unique');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique('sales_orders_tenant_id_order_number_unique');
            $table->unique('order_number', 'sales_orders_order_number_unique');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique('quotations_tenant_id_quotation_number_unique');
            $table->unique('quotation_number', 'quotations_quotation_number_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_tenant_id_product_code_unique');
            $table->unique('product_code', 'products_product_code_unique');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('product_categories_tenant_id_name_unique');
            $table->dropUnique('product_categories_tenant_id_slug_unique');
            $table->unique('name', 'product_categories_name_unique');
            $table->unique('slug', 'product_categories_slug_unique');
        });
    }
};
