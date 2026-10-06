<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Business Documents
        Schema::create('business_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('document_type', 30);
            $table->string('document_number', 100);
            $table->date('document_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('commercial_shipment_id')->nullable()->constrained('commercial_shipments')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('taxable_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('INR');
            $table->string('payment_terms', 150)->nullable();
            $table->string('delivery_location', 255)->nullable();
            $table->string('status', 40)->default('VERIFIED');
            $table->text('notes')->nullable();
            $table->json('extracted_metadata')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('document_type');
            $table->index('document_number');
            $table->index('customer_id');
            $table->index('invoice_id');
            $table->index('status');
        });

        // 2. Document Versions
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Product Documents
        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('document_type', 50)->default('PHOTO'); // PHOTO, SPEC_SHEET, COA, CERTIFICATE
            $table->string('title', 150);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_size_bytes', 50)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->boolean('is_primary_photo')->default(false);
            $table->boolean('is_latest_version')->default(true);
            $table->integer('display_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Product Document Shares (External secure share links)
        Schema::create('product_document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('share_token', 64)->unique();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_email', 150)->nullable();
            $table->string('password_hash')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_document_shares');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('business_documents');
    }
};
