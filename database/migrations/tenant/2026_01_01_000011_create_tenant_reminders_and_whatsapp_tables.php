<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. WhatsApp Configuration per firm / workspace
        Schema::create('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('provider', 30)->default('META_CLOUD'); // META_CLOUD, MOCK
            $table->string('phone_number_id')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('sender_phone_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_send_invoices')->default(true);
            $table->boolean('auto_send_shipments')->default(true);
            $table->boolean('auto_send_receipts')->default(true);
            $table->boolean('auto_send_reminders')->default(true);
            $table->timestamps();
        });

        // 2. WhatsApp Delivery Logs
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->string('recipient_phone', 30);
            $table->string('recipient_name', 150)->nullable();
            $table->string('message_type', 50); // INVOICE, SHIPMENT, RECEIPT, PAYMENT_REMINDER, SERVICE_REMINDER, CUSTOM
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('template_name', 100)->nullable();
            $table->text('message_body')->nullable();
            $table->string('document_url')->nullable();
            $table->string('meta_message_id', 100)->nullable();
            $table->string('status', 30)->default('SENT'); // QUEUED, SENT, DELIVERED, READ, FAILED
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // 3. Payment Reminder Logs (Prevents spam, tracks cadence)
        Schema::create('payment_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('reminder_type', 30); // BEFORE_DUE, ON_DUE, OVERDUE
            $table->string('channel', 20)->default('WHATSAPP'); // WHATSAPP, EMAIL, SMS
            $table->string('recipient_phone', 30)->nullable();
            $table->string('recipient_email', 100)->nullable();
            $table->dateTime('sent_at');
            $table->string('status', 30)->default('SENT');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Machinery & Asset Service Reminders
        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->nullable()->constrained('firms')->nullOnDelete();
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->string('service_name', 150);
            $table->string('service_type', 50)->default('PREVENTIVE_MAINTENANCE'); // PREVENTIVE_MAINTENANCE, CALIBRATION, OIL_GREASE, FILTER_REPLACEMENT, SAFETY_INSPECTION, ELECTRICAL_CHECK, OTHER
            $table->integer('frequency_days')->default(30);
            $table->date('last_serviced_date')->nullable();
            $table->date('next_due_date');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('UPCOMING'); // UPCOMING, DUE, OVERDUE, COMPLETED
            $table->string('priority', 20)->default('MEDIUM'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->decimal('estimated_cost', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
        Schema::dropIfExists('payment_reminder_logs');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('tenant_whatsapp_settings');
    }
};
