<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_documents')) {
            Schema::create('business_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('document_type', 30)->index(); // PO, INVOICE, LR, OTHER
                $table->string('document_number', 100)->index();
                $table->date('document_date')->nullable();
                $table->date('due_date')->nullable();

                // ERP Chain Relationships
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
                $table->foreignId('commercial_shipment_id')->nullable()->constrained('commercial_shipments')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

                // Financial & Commercial Details
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->decimal('taxable_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->string('currency', 10)->default('INR');
                $table->string('payment_terms', 150)->nullable();
                $table->string('delivery_location', 255)->nullable();
                $table->string('status', 40)->default('VERIFIED'); // DRAFT, PENDING_REVIEW, VERIFIED, OVERDUE, PAID, CANCELLED, ARCHIVED
                $table->text('notes')->nullable();

                // Parsed Line Items & OCR Metadata
                $table->json('extracted_metadata')->nullable();
                $table->unsignedInteger('current_version')->default(1);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'document_type']);
                $table->index(['tenant_id', 'document_number']);
                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('document_versions')) {
            Schema::create('document_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('business_documents')->cascadeOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->string('file_path', 255);
                $table->string('file_name', 255);
                $table->unsignedBigInteger('file_size')->default(0); // bytes
                $table->string('mime_type', 100)->nullable();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('change_note', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['document_id', 'version_number']);
                $table->index(['document_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('business_documents');
    }
};
