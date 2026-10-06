<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Central Super-Admin Users
        Schema::create('central_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Subscription Plans
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Plan Feature Entitlements
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('limit_type')->default('numeric'); // numeric, boolean, unlimited
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        // 4. Tenant Usage Tracking
        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('metric_key');
            $table->string('period', 10); // YYYY-MM
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'metric_key', 'period']);
        });

        // 5. Impersonation Audit Logs
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_user_id')->constrained('central_users')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
        Schema::dropIfExists('tenant_usage');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('central_users');
    }
};
