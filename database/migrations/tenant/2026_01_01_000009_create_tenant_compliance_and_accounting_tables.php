<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Transporters
        Schema::create('transporters', function (Blueprint $table) {
            $table->id();
            $table->string('transporter_name', 150);
            $table->string('transporter_id_gst', 50)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->text('branch_address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Vendors
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
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
        });

        // 3. Bank Accounts
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
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
        });

        // 4. Credit / Debit Notes
        Schema::create('credit_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('note_type', 20)->default('CREDIT_NOTE');
            $table->string('note_number', 50)->unique();
            $table->date('note_date');
            $table->string('original_invoice_number', 50)->nullable();
            $table->string('reason', 150)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->string('status', 30)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('po_number', 50)->unique();
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
        });

        // 6. Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('item_description', 255);
            $table->string('hsn_code', 30)->nullable();
            $table->decimal('quantity', 12, 2)->default(0.00);
            $table->string('uom', 20)->default('KG');
            $table->decimal('rate', 12, 2)->default(0.00);
            $table->decimal('tax_rate_percent', 5, 2)->default(5.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->timestamps();
        });

        // 7. Chart of Accounts (Double-Entry Core)
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 50)->unique();
            $table->string('account_name', 150);
            $table->string('account_group', 50); // ASSETS, LIABILITIES, EQUITY, INCOME, EXPENSES
            $table->string('sub_group', 50); // CURRENT_ASSETS, SUNDRY_DEBTORS, BANK, TAX_INPUT, TAX_OUTPUT
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->boolean('is_system')->default(false); // Protected default ledgers
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 8. Journal Vouchers
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('voucher_number', 50)->unique();
            $table->date('voucher_date');
            $table->string('voucher_type', 30)->default('JOURNAL'); // JOURNAL, SALES, PURCHASE, PAYMENT, RECEIPT, CONTRA, DEPRECIATION
            $table->string('reference_type', 50)->nullable(); // INVOICE, EXPENSE, ASSET, PAYMENT
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('narration')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0.00);
            $table->decimal('total_credit', 15, 2)->default(0.00);
            $table->timestamps();
        });

        // 9. Journal Voucher Items (Debit must equal Credit)
        Schema::create('journal_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_voucher_id')->constrained('journal_vouchers')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->decimal('debit_amount', 15, 2)->default(0.00);
            $table->decimal('credit_amount', 15, 2)->default(0.00);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 10. Expenses with ITC
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('expense_category', 100);
            $table->string('vendor_invoice_number', 100)->nullable();
            $table->date('expense_date');
            $table->decimal('base_amount', 15, 2)->default(0.00);
            $table->decimal('gst_rate', 5, 2)->default(0.00);
            $table->decimal('cgst_amount', 15, 2)->default(0.00);
            $table->decimal('sgst_amount', 15, 2)->default(0.00);
            $table->decimal('igst_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->boolean('is_itc_eligible')->default(true); // Input Tax Credit eligibility
            $table->string('itc_type', 50)->default('INPUT_SERVICES'); // INPUT_GOODS, INPUT_SERVICES, CAPITAL_GOODS, INELIGIBLE
            $table->string('payment_status', 30)->default('PAID');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Fixed Assets Register
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('asset_code', 50)->unique();
            $table->string('asset_name', 150);
            $table->string('asset_category', 50)->default('PLANT_MACHINERY'); // PLANT_MACHINERY, VEHICLES, COMPUTERS, FURNITURE
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0.00);
            $table->integer('useful_life_years')->default(10);
            $table->string('depreciation_method', 20)->default('SLM'); // SLM, WDV
            $table->decimal('depreciation_rate', 5, 2)->default(10.00);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0.00);
            $table->decimal('current_book_value', 15, 2);
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, DISPOSED, WRITTEN_OFF
            $table->timestamps();
        });

        // 12. Asset Depreciations Schedule
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('depreciation_date');
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('book_value_before', 15, 2);
            $table->decimal('book_value_after', 15, 2);
            $table->foreignId('journal_voucher_id')->nullable()->constrained('journal_vouchers')->nullOnDelete();
            $table->timestamps();
        });

        // 13. E-way Bills
        Schema::create('eway_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_shipment_id')->constrained('commercial_shipments')->cascadeOnDelete();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('eway_bill_number', 50)->unique();
            $table->dateTime('generated_at');
            $table->dateTime('valid_until');
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, CANCELLED, EXTENDED
            $table->string('doc_type', 10)->default('INV');
            $table->string('supply_type', 20)->default('OUTWARD');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eway_bills');
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journal_voucher_items');
        Schema::dropIfExists('journal_vouchers');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('credit_debit_notes');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('transporters');
    }
};
