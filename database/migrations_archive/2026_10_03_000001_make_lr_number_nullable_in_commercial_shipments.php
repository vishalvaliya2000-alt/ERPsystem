<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->string('lr_number')->nullable()->change();
            $table->string('transporter')->nullable()->default('TBD')->change();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->string('lr_number')->nullable(false)->change();
            $table->string('transporter')->default('ACPL')->nullable(false)->change();
        });
    }
};
