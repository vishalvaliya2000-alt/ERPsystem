<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable(false)->change();
        });
    }
};
