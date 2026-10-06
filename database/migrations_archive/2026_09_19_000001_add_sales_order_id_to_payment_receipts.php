<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->foreignId('sales_order_id')->nullable()->after('invoice_id')->constrained('sales_orders')->nullOnDelete();
            $table->string('receipt_type')->default('INVOICE_PAYMENT')->after('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn(['sales_order_id', 'receipt_type']);
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });
    }
};
