<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('linked_po_numbers')->nullable()->after('commercial_shipment_id');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('commercial_shipment_item_id')->nullable()->constrained('commercial_shipment_items')->nullOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('linked_po_numbers');
        });
    }
};
