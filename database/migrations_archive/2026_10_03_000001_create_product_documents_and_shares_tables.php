<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_documents')) {
            Schema::create('product_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('document_type', 30)->index(); // PHOTO, COA, SPECIFICATION, MSDS, OTHER
                $table->string('title', 255)->nullable();
                $table->string('file_path', 255);
                $table->string('file_name', 255);
                $table->unsignedBigInteger('file_size')->default(0); // in bytes
                $table->string('mime_type', 100)->nullable();
                $table->string('version', 50)->nullable();
                $table->date('valid_until')->nullable(); // especially for COA/Certificates
                $table->boolean('is_latest')->default(true)->index();
                $table->boolean('is_primary')->default(false)->index(); // for photos
                $table->unsignedInteger('sort_order')->default(0); // for ordering photos/docs
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'product_id']);
                $table->index(['product_id', 'document_type']);
                $table->index(['product_id', 'is_latest']);
            });
        }

        if (! Schema::hasTable('product_document_shares')) {
            Schema::create('product_document_shares', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('share_token', 64)->unique();
                $table->string('title', 255)->nullable();
                $table->string('recipient_email', 255)->nullable();
                $table->json('selected_document_ids')->nullable(); // array of product_document ids
                $table->string('password_hash', 255)->nullable(); // optional password/PIN protection
                $table->dateTime('expires_at')->nullable();
                $table->unsignedInteger('views_count')->default(0);
                $table->dateTime('last_accessed_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'product_id']);
                $table->index(['share_token', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_document_shares');
        Schema::dropIfExists('product_documents');
    }
};
