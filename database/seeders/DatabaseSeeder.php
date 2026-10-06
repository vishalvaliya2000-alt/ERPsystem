<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Default Tenant
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Real Dehydrates ERP',
                'slug' => 'real-dehydrates',
                'currency_code' => 'INR',
                'currency_symbol' => '₹',
                'plan' => 'ENTERPRISE',
                'is_active' => true,
            ]
        );

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => bcrypt('password123'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'role' => 'ADMIN',
            ]
        );

        if (method_exists($tenant, 'users')) {
            $tenant->users()->syncWithoutDetaching([$admin->id => ['role' => 'ADMIN', 'is_default' => true]]);
        }

        // 2. Product Categories
        $garlic = ProductCategory::firstOrCreate(
            ['slug' => 'garlic'],
            ['name' => 'Garlic', 'description' => 'Dehydrated Garlic Products']
        );

        $onion = ProductCategory::firstOrCreate(
            ['slug' => 'onion'],
            ['name' => 'Onion', 'description' => 'Dehydrated Onion Products']
        );

        // 3. Master Products Catalog (Garlic & Onion Granulations)
        $products = [
            // Garlic
            ['code' => 'GC001', 'name' => 'Garlic Chopped', 'cat' => $garlic->id, 'rate' => 120.0, 'hsn' => '07129090'],
            ['code' => 'GF001', 'name' => 'Garlic Flakes', 'cat' => $garlic->id, 'rate' => 120.0, 'hsn' => '07129090'],
            ['code' => 'GG001', 'name' => 'Garlic Granules', 'cat' => $garlic->id, 'rate' => 120.0, 'hsn' => '07129090'],
            ['code' => 'GM001', 'name' => 'Garlic Minced', 'cat' => $garlic->id, 'rate' => 120.0, 'hsn' => '07129090'],
            ['code' => 'GP001', 'name' => 'Garlic Powder', 'cat' => $garlic->id, 'rate' => 120.0, 'hsn' => '07129090'],
            ['code' => 'TF001', 'name' => 'Toasted Garlic Flakes', 'cat' => $garlic->id, 'rate' => 135.0, 'hsn' => '07129090'],
            ['code' => 'TG001', 'name' => 'Toasted Garlic Granules', 'cat' => $garlic->id, 'rate' => 135.0, 'hsn' => '07129090'],
            ['code' => 'TM001', 'name' => 'Toasted Garlic Minced', 'cat' => $garlic->id, 'rate' => 135.0, 'hsn' => '07129090'],
            ['code' => 'TP001', 'name' => 'Toasted Garlic Powder', 'cat' => $garlic->id, 'rate' => 135.0, 'hsn' => '07129090'],

            // Onion (White & Pink)
            ['code' => 'WOC001', 'name' => 'White Onion Chopped', 'cat' => $onion->id, 'rate' => 110.0, 'hsn' => '07129090'],
            ['code' => 'WOF001', 'name' => 'White Onion Flakes', 'cat' => $onion->id, 'rate' => 110.0, 'hsn' => '07129090'],
            ['code' => 'WOG001', 'name' => 'White Onion Granules', 'cat' => $onion->id, 'rate' => 110.0, 'hsn' => '07129090'],
            ['code' => 'WOM001', 'name' => 'White Onion Minced', 'cat' => $onion->id, 'rate' => 110.0, 'hsn' => '07129090'],
            ['code' => 'WOP001', 'name' => 'White Onion Powder', 'cat' => $onion->id, 'rate' => 110.0, 'hsn' => '07129090'],
            ['code' => 'POC001', 'name' => 'Pink Onion Chopped', 'cat' => $onion->id, 'rate' => 115.0, 'hsn' => '07129090'],
            ['code' => 'POF001', 'name' => 'Pink Onion Flakes', 'cat' => $onion->id, 'rate' => 115.0, 'hsn' => '07129090'],
            ['code' => 'POG001', 'name' => 'Pink Onion Granules', 'cat' => $onion->id, 'rate' => 115.0, 'hsn' => '07129090'],
            ['code' => 'POM001', 'name' => 'Pink Onion Minced', 'cat' => $onion->id, 'rate' => 115.0, 'hsn' => '07129090'],
            ['code' => 'POP001', 'name' => 'Pink Onion Powder', 'cat' => $onion->id, 'rate' => 115.0, 'hsn' => '07129090'],
            ['code' => 'TWOF001', 'name' => 'Toasted White Onion Flakes', 'cat' => $onion->id, 'rate' => 130.0, 'hsn' => '07129090'],
            ['code' => 'TWOP001', 'name' => 'Toasted White Onion Powder', 'cat' => $onion->id, 'rate' => 130.0, 'hsn' => '07129090'],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(
                ['product_code' => $p['code']],
                [
                    'product_name' => $p['name'],
                    'category_id' => $p['cat'],
                    'uom' => 'KG',
                    'standard_rate' => $p['rate'],
                    'standard_cost' => $p['rate'] * 0.75,
                    'hsn_code' => $p['hsn'],
                    'packaging' => '25 KG Bag / Carton',
                    'is_active' => true,
                ]
            );
        }
    }
}
