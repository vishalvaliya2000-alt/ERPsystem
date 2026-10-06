<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('commercial_shipment_id')->nullable()->after('sales_order_id')->constrained('commercial_shipments')->nullOnDelete();
            $table->decimal('material_subtotal', 14, 2)->default(0)->after('due_date');
            $table->decimal('freight_amount', 12, 2)->default(0)->after('material_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['commercial_shipment_id']);
            $table->dropColumn(['commercial_shipment_id', 'material_subtotal', 'freight_amount']);
        });
    }
};
