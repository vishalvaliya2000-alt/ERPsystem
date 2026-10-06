<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_shipment_items') && ! Schema::hasColumn('commercial_shipment_items', 'batch_number')) {
            Schema::table('commercial_shipment_items', function (Blueprint $table) {
                $table->string('batch_number', 50)->nullable()->after('sales_order_item_id');
            });
        }

        if (Schema::hasTable('invoice_items') && ! Schema::hasColumn('invoice_items', 'batch_number')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('batch_number', 50)->nullable()->after('commercial_shipment_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commercial_shipment_items') && Schema::hasColumn('commercial_shipment_items', 'batch_number')) {
            Schema::table('commercial_shipment_items', function (Blueprint $table) {
                $table->dropColumn('batch_number');
            });
        }

        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'batch_number')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn('batch_number');
            });
        }
    }
};
