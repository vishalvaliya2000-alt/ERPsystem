<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->unique('lr_number');
        });

        Schema::table('samples', function (Blueprint $table) {
            $table->unique('awb_number');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_shipments', function (Blueprint $table) {
            $table->dropUnique(['lr_number']);
        });

        Schema::table('samples', function (Blueprint $table) {
            $table->dropUnique(['awb_number']);
        });
    }
};
