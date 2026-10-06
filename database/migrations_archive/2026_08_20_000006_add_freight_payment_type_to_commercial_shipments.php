<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->string('freight_payment_type')->default('TO_PAY')->after('delivery_type'); // TO_PAY, PAID
        });
    }

    public function down(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->dropColumn('freight_payment_type');
        });
    }
};
