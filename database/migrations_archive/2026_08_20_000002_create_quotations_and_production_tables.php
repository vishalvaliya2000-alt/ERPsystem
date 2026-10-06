<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Quotations Master
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_company')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->dateTime('quotation_date')->useCurrent();
            $table->dateTime('valid_until');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(5.00); // 5% GST for dehydrated agricultural products
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('payment_terms')->default('30 Days Credit');
            $table->string('freight_terms')->default('FOR Destination');
            $table->string('delivery_timeline')->default('7-10 Working Days from PO');
            $table->string('status')->default('SENT'); // DRAFT, SENT, ACCEPTED, REJECTED, EXPIRED, CONVERTED
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Quotation Items
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('uom')->default('KG');
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->string('packaging')->default('25 KG Bags');
            $table->text('specifications')->nullable();
            $table->timestamps();
        });

        // 3. Production Batches
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->dateTime('production_date')->useCurrent();
            $table->decimal('batch_qty', 12, 2);
            $table->decimal('available_qty', 12, 2);
            $table->decimal('moisture_percentage', 5, 2)->default(5.5);
            $table->string('sensory_grade')->default('Premium Export Grade');
            $table->string('raw_lot_number')->nullable();
            $table->string('status')->default('QUALITY_APPROVED'); // IN_PROCESS, QUALITY_CHECK, QUALITY_APPROVED, PACKED, EXHAUSTED
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Inventory Stocks
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->decimal('current_stock_qty', 12, 2)->default(0);
            $table->decimal('min_threshold_qty', 12, 2)->default(5000);
            $table->string('godown_location')->default('Bhavnagar Plant Godown A');
            $table->dateTime('last_audited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
