<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Companies (Legal parent entities)
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('cin')->nullable();
            $table->string('pan')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Firms (Operating units / GSTIN locations)
        Schema::create('firms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('firm_name');
            $table->string('gstin')->nullable();
            $table->string('state')->default('Gujarat');
            $table->string('state_code', 5)->default('24');
            $table->string('pan')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('lut_arn')->nullable();
            $table->string('iec_code')->nullable();
            $table->string('fssai_number')->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->string('quotation_prefix', 20)->default('QUO');
            $table->string('po_prefix', 20)->default('PO');
            $table->string('shipment_prefix', 20)->default('SHP');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_branch')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
        Schema::dropIfExists('companies');
    }
};
