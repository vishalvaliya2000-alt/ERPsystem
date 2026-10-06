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
            $table->decimal('standard_cost', 12, 2)->default(0);
            $table->string('hsn_code')->default('07129090');
            $table->decimal('min_order_qty', 12, 2)->default(50);
            $table->string('packaging')->default('25 KG Bag / Carton');
            $table->string('specification_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
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
            $table->integer('payment_terms_days')->default(30);
            $table->string('stage')->default('REPEAT_CUSTOMER'); // LEAD, TRIAL, QUOTED, NEGOTIATION, FIRST_ORDER, REPEAT_CUSTOMER, DORMANT
            $table->string('health_score')->default('HEALTHY'); // HEALTHY, NEEDS_ATTENTION, AT_RISK, DORMANT
            $table->string('status')->default('Active'); // Active, Inactive, Blocked
            $table->string('primary_contact_person')->nullable();
            $table->string('primary_phone')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('country')->default('India');
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->dateTime('last_order_date')->nullable();
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
            $table->string('stage')->default('LEAD'); // LEAD, CONTACTED, INTERESTED, SAMPLE_REQUESTED, QUOTATION_SENT, NEGOTIATION, WON, LOST
            $table->string('priority')->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
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
            $table->string('delivery_status')->default('PREPARED'); // PREPARED, DISPATCHED, IN_TRANSIT, DELIVERED, FEEDBACK_PENDING, TRIAL, APPROVED, REJECTED, ORDERED
            $table->dateTime('delivered_at')->nullable();
            $table->string('feedback_rating')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->string('trial_status')->default('Pending');
            $table->text('trial_result')->nullable();
            $table->string('next_action')->default('Track courier delivery');
            $table->dateTime('next_action_date')->nullable();
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
            $table->string('status')->default('CONFIRMED'); // DRAFT, CONFIRMED, ADVANCE_PENDING, PRODUCTION, READY, PARTIALLY_DISPATCHED, DISPATCHED, DELIVERED, COMPLETED, CANCELLED
            $table->text('notes')->nullable();
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
            $table->string('status')->default('PENDING'); // PENDING, PARTIAL, COMPLETED
            $table->timestamps();
        });

        // 10. Commercial Shipments (Freight / Transporter)
        Schema::create('commercial_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->dateTime('shipment_date')->useCurrent();
            $table->string('transporter')->default('ACPL');
            $table->string('lr_number');
            $table->string('vehicle_number')->nullable();
            $table->string('destination')->nullable();
            $table->string('delivery_type')->default('Door Delivery');
            $table->decimal('freight_amount', 12, 2)->default(0);
            $table->string('status')->default('DISPATCHED'); // PLANNED, PICKUP_PENDING, DISPATCHED, IN_TRANSIT, REACHED_DESTINATION, DELIVERED, CONFIRMED, CLOSED
            $table->dateTime('expected_delivery_date')->nullable();
            $table->dateTime('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_document_url')->nullable();
            $table->timestamps();
        });

        // 11. Commercial Shipment Items
        Schema::create('commercial_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_shipment_id')->constrained('commercial_shipments')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_value', 12, 2)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->timestamps();
        });

        // 12. Invoices Master
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->dateTime('invoice_date')->useCurrent();
            $table->dateTime('due_date');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_received', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('status')->default('PENDING'); // DRAFT, ISSUED, PART_PAID, PAID, DUE_SOON, OVERDUE
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 13. Payment Receipts
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->dateTime('receipt_date')->useCurrent();
            $table->decimal('amount_received', 14, 2);
            $table->string('payment_mode')->default('UPI'); // UPI, NEFT, RTGS, CHEQUE, CASH
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 14. Follow-up & Task Engine
        Schema::create('followup_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->string('type')->default('SALES_FOLLOWUP'); // SALES_FOLLOWUP, SAMPLE_FEEDBACK, PO_CONFIRMATION, PAYMENT_FOLLOWUP, DELIVERY_CONFIRMATION, RE_ENGAGEMENT, INTERNAL_TASK
            $table->text('reason');
            $table->string('priority')->default('MEDIUM'); // URGENT, HIGH, MEDIUM, LOW
            $table->dateTime('due_date');
            $table->string('status')->default('PENDING'); // PENDING, COMPLETED, SNOOZED, CANCELLED
            $table->text('pending_item')->nullable();
            $table->text('next_action');
            $table->text('outcome_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('snooze_until')->nullable();
            $table->timestamps();
        });

        // 15. Activity & Communication Stream
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->string('activity_type'); // NOTE, CALL, WHATSAPP, EMAIL, STATUS_CHANGE, PAYMENT, SHIPMENT, STAGE_CHANGE
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata_json')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
            $table->timestamps();
        });

        // 16. Proactive AI Insights
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('insight_type'); // REPEAT_ORDER, CROSS_SELL, UPSELL, AT_RISK, DORMANT, PAYMENT_RISK
            $table->string('priority')->default('MEDIUM');
            $table->decimal('score', 5, 2)->default(0);
            $table->string('summary');
            $table->text('rationale');
            $table->text('recommended_action');
            $table->string('action_url')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();
        });

        // 17. System Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('title');
            $table->text('message');
            $table->string('priority')->default('MEDIUM');
            $table->boolean('read')->default(false);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('followup_tasks');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('commercial_shipment_items');
        Schema::dropIfExists('commercial_shipments');
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
