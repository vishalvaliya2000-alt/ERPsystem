<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Products Master
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('uom')->default('KG');
            $table->decimal('standard_rate', 12, 2)->default(0);
            $table->decimal('standard_cost', 12, 2)->default(0); // Cost price for margin/profit calculation
            $table->string('hsn_code')->default('07129090');
            $table->decimal('min_order_qty', 12, 2)->default(50);
            $table->string('packaging')->default('25 KG Bag / Carton');
            $table->string('specification_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 3. Customers Master
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('company_name');
            $table->string('trade_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->string('credit_limit_action', 10)->default('WARN'); // WARN, BLOCK
            $table->integer('payment_terms_days')->default(30);
            $table->string('stage')->default('REPEAT_CUSTOMER');
            $table->string('health_score')->default('HEALTHY');
            $table->string('status')->default('Active');
            $table->string('primary_contact_person')->nullable();
            $table->string('primary_phone')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->default('Gujarat');
            $table->string('state_code', 5)->default('24');
            $table->string('pincode', 10)->nullable();
            $table->string('country')->default('India');
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->dateTime('last_order_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 4. Customer Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Leads / Prospects Pipeline
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->default('Gujarat');
            $table->string('country')->default('India');
            $table->string('source')->default('Direct / Website');
            $table->string('interested_products')->nullable();
            $table->decimal('estimated_value', 14, 2)->default(0);
            $table->string('stage')->default('LEAD');
            $table->string('priority')->default('MEDIUM');
            $table->string('next_action')->nullable();
            $table->dateTime('next_action_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Samples Evaluation
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('uom')->default('KG');
            $table->string('batch_number')->nullable();
            $table->string('sample_type')->default('Commercial Evaluation');
            $table->string('courier_provider')->default('DTDC');
            $table->string('awb_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('delivery_status')->default('PREPARED');
            $table->dateTime('delivered_at')->nullable();
            $table->string('feedback_rating')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->string('trial_status')->default('Pending');
            $table->text('trial_result')->nullable();
            $table->string('next_action')->default('Track courier delivery');
            $table->dateTime('next_action_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 7. Courier Shipments
        Schema::create('courier_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('samples')->cascadeOnDelete();
            $table->string('tracking_number');
            $table->string('provider');
            $table->dateTime('shipment_date')->nullable();
            $table->string('status')->default('LABEL_CREATED');
            $table->dateTime('last_status_update')->nullable();
            $table->dateTime('estimated_delivery')->nullable();
            $table->dateTime('actual_delivery')->nullable();
            $table->json('history_json')->nullable();
            $table->timestamps();
        });

        // 8. Sales Orders
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('po_number')->nullable();
            $table->dateTime('po_date')->nullable();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->dateTime('order_date')->useCurrent();
            $table->dateTime('expected_dispatch_date')->nullable();
            $table->dateTime('actual_dispatch_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('advance_required', 14, 2)->default(0);
            $table->decimal('advance_received', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->string('payment_terms')->default('30 Days Credit');
            $table->string('status')->default('CONFIRMED');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 9. Sales Order Items
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('order_qty', 12, 2);
            $table->decimal('rate', 12, 2);
            $table->decimal('order_value', 14, 2);
            $table->decimal('shipped_qty', 12, 2)->default(0);
            $table->decimal('balance_qty', 12, 2);
            $table->string('status')->default('PENDING');
            $table->timestamps();
        });

        // 10. Sales Order Revisions
        Schema::create('sales_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->integer('revision_number')->default(1);
            $table->text('change_summary')->nullable();
            $table->json('previous_state_json')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 11. Commercial Shipments
        Schema::create('commercial_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('shipment_number')->unique();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->dateTime('shipment_date')->useCurrent();
            $table->string('transporter')->default('ACPL');
            $table->string('lr_number')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('destination')->nullable();
            $table->string('delivery_type')->default('Door Delivery');
            $table->decimal('freight_amount', 12, 2)->default(0);
            $table->string('freight_payment_type', 20)->default('PAID');
            $table->string('status')->default('DISPATCHED');
            $table->dateTime('expected_delivery_date')->nullable();
            $table->dateTime('actual_delivery_date')->nullable();
            $table->string('proof_document_url')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 12. Commercial Shipment Items
        Schema::create('commercial_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_shipment_id')->constrained('commercial_shipments')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_value', 12, 2)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->timestamps();
        });

        // 13. Invoices Master
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('commercial_shipment_id')->nullable()->constrained('commercial_shipments')->nullOnDelete();
            $table->string('linked_po_numbers')->nullable();
            $table->dateTime('invoice_date')->useCurrent();
            $table->dateTime('due_date');
            $table->decimal('material_subtotal', 14, 2)->default(0);
            $table->decimal('freight_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('tcs_rate', 5, 3)->default(0);
            $table->decimal('tcs_amount', 14, 2)->default(0);
            $table->decimal('tds_rate', 5, 3)->default(0);
            $table->decimal('tds_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_received', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->decimal('total_cost_amount', 14, 2)->default(0); // Cost check
            $table->decimal('gross_profit_amount', 14, 2)->default(0); // Profit check
            $table->decimal('gross_profit_percentage', 6, 2)->default(0);
            $table->string('status')->default('PENDING');
            $table->boolean('ads_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 14. Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
            $table->foreignId('commercial_shipment_id')->nullable()->constrained('commercial_shipments')->nullOnDelete();
            $table->foreignId('commercial_shipment_item_id')->nullable()->constrained('commercial_shipment_items')->nullOnDelete();
            $table->string('description');
            $table->string('hsn_code', 20)->default('07129090');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('uom', 20)->default('KG');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_cost', 14, 2)->default(0);
            $table->decimal('line_profit', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(5.00);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        // 15. Payment Receipts
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('receipt_type')->default('INVOICE_PAYMENT');
            $table->dateTime('receipt_date')->useCurrent();
            $table->decimal('amount_received', 14, 2);
            $table->string('payment_mode')->default('UPI');
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 16. Follow-up Tasks
        Schema::create('followup_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->string('type')->default('SALES_FOLLOWUP');
            $table->text('reason');
            $table->dateTime('due_date');
            $table->string('priority')->default('MEDIUM');
            $table->string('status')->default('PENDING');
            $table->string('pending_item')->nullable();
            $table->string('next_action')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        // 17. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->string('activity_type')->default('NOTE');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata_json')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('followup_tasks');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('commercial_shipment_items');
        Schema::dropIfExists('commercial_shipments');
        Schema::dropIfExists('sales_order_revisions');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('courier_shipments');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
