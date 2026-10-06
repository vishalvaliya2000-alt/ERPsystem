<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add revision tracking columns to sales_orders if not present
        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'revision_number')) {
                $table->unsignedInteger('revision_number')->default(0)->after('status');
            }
            if (! Schema::hasColumn('sales_orders', 'last_revised_at')) {
                $table->dateTime('last_revised_at')->nullable()->after('revision_number');
            }
            if (! Schema::hasColumn('sales_orders', 'last_revision_reason')) {
                $table->text('last_revision_reason')->nullable()->after('last_revised_at');
            }
        });

        // 2. Create sales_order_revisions table for immutable audit snapshots
        if (! Schema::hasTable('sales_order_revisions')) {
            Schema::create('sales_order_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->text('revision_reason')->nullable();
                $table->foreignId('revised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revised_by_name')->nullable();
                $table->json('snapshot');
                $table->timestamps();

                $table->index(['tenant_id', 'sales_order_id']);
                $table->index(['sales_order_id', 'revision_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_revisions');

        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'last_revision_reason')) {
                $table->dropColumn('last_revision_reason');
            }
            if (Schema::hasColumn('sales_orders', 'last_revised_at')) {
                $table->dropColumn('last_revised_at');
            }
            if (Schema::hasColumn('sales_orders', 'revision_number')) {
                $table->dropColumn('revision_number');
            }
        });
    }
};
