<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('tagline')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('industry')->nullable();
                $table->string('currency_code', 10)->default('INR');
                $table->string('currency_symbol', 10)->default('₹');
                $table->string('tax_id_label', 50)->default('GSTIN');
                $table->string('tax_id_number', 50)->nullable();
                $table->string('pan_number', 50)->nullable();
                $table->string('iec_code', 50)->nullable();
                $table->string('lut_arn', 100)->nullable();
                $table->string('fssai_number', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('website')->nullable();
                $table->string('address_line')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('pincode', 20)->nullable();
                $table->string('country', 100)->default('India');
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account_number', 50)->nullable();
                $table->string('bank_ifsc_code', 50)->nullable();
                $table->string('bank_swift_code', 50)->nullable();
                $table->string('bank_branch', 100)->nullable();
                $table->string('invoice_prefix', 20)->default('INV');
                $table->string('quotation_prefix', 20)->default('QUO');
                $table->string('po_prefix', 20)->default('PO');
                $table->string('shipment_prefix', 20)->default('SHP');
                $table->string('plan', 50)->default('ENTERPRISE');
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 50)->default('ADMIN');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
