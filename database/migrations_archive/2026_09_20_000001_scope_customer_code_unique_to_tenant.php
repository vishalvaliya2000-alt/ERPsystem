<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop global unique constraint on customer_code if present
            $table->dropUnique('customers_customer_code_unique');
            // Add compound unique constraint scoped by tenant_id
            $table->unique(['tenant_id', 'customer_code'], 'customers_tenant_id_customer_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_tenant_id_customer_code_unique');
            $table->unique('customer_code', 'customers_customer_code_unique');
        });
    }
};
