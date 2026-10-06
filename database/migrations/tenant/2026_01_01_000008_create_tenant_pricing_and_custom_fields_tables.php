<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Multiple Pricing Tiers / Quantity Slabs
        Schema::create('product_price_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('tier_name'); // Wholesale, Distributor, Export, Retail
            $table->decimal('min_quantity', 12, 2)->default(0);
            $table->decimal('rate', 12, 2);
            $table->timestamps();
        });

        // 2. Party-Specific Rate Overrides
        Schema::create('party_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('custom_rate', 12, 2);
            $table->string('custom_hsn', 20)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });

        // 3. Product Custom Fields Definitions
        Schema::create('product_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_name');
            $table->string('field_key')->unique();
            $table->string('field_type')->default('TEXT'); // TEXT, NUMBER, DATE, DROPDOWN
            $table->json('options')->nullable(); // Dropdown options
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        // 4. Product Custom Field Values
        Schema::create('product_custom_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('product_custom_fields')->cascadeOnDelete();
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_custom_values');
        Schema::dropIfExists('product_custom_fields');
        Schema::dropIfExists('party_price_overrides');
        Schema::dropIfExists('product_price_slabs');
    }
};
