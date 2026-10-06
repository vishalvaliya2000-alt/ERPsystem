<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transporters')) {
            Schema::create('transporters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('transporter_name', 150);
                $table->string('transporter_id_gst', 50)->nullable();
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->text('branch_address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'transporter_name']);
            });
        }

        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('vendor_code', 50)->nullable();
                $table->string('company_name', 150);
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('gstin', 30)->nullable();
                $table->string('pan', 30)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('country', 100)->default('India');
                $table->text('address')->nullable();
                $table->integer('payment_terms_days')->default(15);
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account_number', 50)->nullable();
                $table->string('bank_ifsc', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'company_name']);
                $table->index(['tenant_id', 'vendor_code']);
            });
        }

        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('account_title', 150);
                $table->string('bank_name', 150);
                $table->string('account_number', 50);
                $table->string('account_type', 50)->default('CURRENT');
                $table->string('ifsc_code', 30)->nullable();
                $table->string('swift_code', 30)->nullable();
                $table->string('branch_name', 150)->nullable();
                $table->string('currency', 10)->default('INR');
                $table->decimal('opening_balance', 15, 2)->default(0.00);
                $table->decimal('current_balance', 15, 2)->default(0.00);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('credit_debit_notes')) {
            Schema::create('credit_debit_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->string('note_type', 20)->default('CREDIT_NOTE');
                $table->string('note_number', 50);
                $table->date('note_date');
                $table->string('original_invoice_number', 50)->nullable();
                $table->string('reason', 150)->nullable();
                $table->decimal('subtotal', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->string('status', 30)->default('ACTIVE');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'note_number']);
                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'note_type']);
            });
        }

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->string('po_number', 50);
                $table->date('po_date');
                $table->date('expected_delivery_date')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->string('status', 30)->default('DRAFT');
                $table->string('payment_terms', 100)->nullable();
                $table->text('delivery_location')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'po_number']);
                $table->index(['tenant_id', 'vendor_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('item_description', 255);
                $table->string('hsn_code', 30)->nullable();
                $table->decimal('quantity', 12, 2)->default(0.00);
                $table->string('uom', 20)->default('KG');
                $table->decimal('rate', 12, 2)->default(0.00);
                $table->decimal('tax_rate_percent', 5, 2)->default(5.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->timestamps();

                $table->index(['tenant_id', 'purchase_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('credit_debit_notes');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('transporters');
    }
};
