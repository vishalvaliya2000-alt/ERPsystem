<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralPlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super-Admin User
        CentralUser::firstOrCreate([
            'email' => 'admin@erpsaas.in',
        ], [
            'name' => 'Super Administrator',
            'password' => Hash::make('Admin123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // 2. Standard Plan
        $standard = Plan::firstOrCreate([
            'slug' => 'standard',
        ], [
            'name' => 'Standard',
            'price_monthly' => 1999.00,
            'price_yearly' => 19990.00,
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $standardFeatures = [
            ['feature_key' => 'companies_limit', 'limit_type' => 'numeric', 'value' => '3', 'description' => 'Up to 3 parent companies'],
            ['feature_key' => 'firms_limit', 'limit_type' => 'numeric', 'value' => '3', 'description' => 'Up to 3 GSTIN operating firms'],
            ['feature_key' => 'eway_bills_monthly_limit', 'limit_type' => 'numeric', 'value' => '10', 'description' => '10 E-way bills per month'],
            ['feature_key' => 'restore_deleted_limit', 'limit_type' => 'numeric', 'value' => '2', 'description' => 'Restore up to 2 deleted records'],
            ['feature_key' => 'invoice_ads_removed', 'limit_type' => 'boolean', 'value' => 'false', 'description' => 'Invoice branding banner displayed'],
        ];

        foreach ($standardFeatures as $f) {
            PlanFeature::firstOrCreate([
                'plan_id' => $standard->id,
                'feature_key' => $f['feature_key'],
            ], $f);
        }

        // 3. Premium Plan
        $premium = Plan::firstOrCreate([
            'slug' => 'premium',
        ], [
            'name' => 'Premium',
            'price_monthly' => 4999.00,
            'price_yearly' => 49990.00,
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $premiumFeatures = [
            ['feature_key' => 'companies_limit', 'limit_type' => 'numeric', 'value' => '5', 'description' => 'Up to 5 parent companies'],
            ['feature_key' => 'firms_limit', 'limit_type' => 'numeric', 'value' => '5', 'description' => 'Up to 5 GSTIN operating firms'],
            ['feature_key' => 'eway_bills_monthly_limit', 'limit_type' => 'unlimited', 'value' => '-1', 'description' => 'Unlimited E-way bills'],
            ['feature_key' => 'restore_deleted_limit', 'limit_type' => 'unlimited', 'value' => '-1', 'description' => 'Unlimited restore from recycle bin'],
            ['feature_key' => 'invoice_ads_removed', 'limit_type' => 'boolean', 'value' => 'true', 'description' => 'Advertisements removed on invoices'],
        ];

        foreach ($premiumFeatures as $f) {
            PlanFeature::firstOrCreate([
                'plan_id' => $premium->id,
                'feature_key' => $f['feature_key'],
            ], $f);
        }
    }
}
