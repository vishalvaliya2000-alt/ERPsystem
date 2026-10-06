<?php

namespace Database\Seeders;

use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tenant
        $tenant = Tenant::firstOrCreate(['id' => 1], [
            'name' => 'Acme Industries (TEST ENV)',
            'slug' => 'test-acme',
        ]);

        // 2. Admin User
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Test Administrator',
                'password' => bcrypt('password123'),
            ]
        );
        $tenant->users()->syncWithoutDetaching([$user->id => ['role' => 'admin']]);

        // Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner']);
        $user->assignRole($ownerRole);

        // 3. Product Categories & Products
        $garlic = ProductCategory::firstOrCreate(['slug' => 'garlic'], ['name' => 'Garlic']);
        $product = Product::firstOrCreate(
            ['product_code' => 'DEMO-PROD-A'],
            [
                'tenant_id' => $tenant->id,
                'product_name' => 'Demo Product A',
                'category_id' => $garlic->id,
                'uom' => 'KG',
                'standard_rate' => 120.0,
                'standard_cost' => 90.0,
                'hsn_code' => '07129090',
                'packaging' => '25 KG Bag',
                'is_active' => true,
            ]
        );

        // 4. Customers
        $customerA = Customer::firstOrCreate(
            ['customer_code' => 'DEMO-CUST-A'],
            [
                'tenant_id' => $tenant->id,
                'company_name' => 'Demo Customer A',
                'gst_number' => '24DEMOA0001Z1',
            ]
        );

        $customerB = Customer::firstOrCreate(
            ['customer_code' => 'DEMO-CUST-B'],
            [
                'tenant_id' => $tenant->id,
                'company_name' => 'Demo Customer B',
                'gst_number' => '24DEMOB0001Z1',
            ]
        );

        // 5. Orders & Shipments & Invoices
        $order = SalesOrder::firstOrCreate(
            ['order_number' => 'DEMO-ORD-1'],
            [
                'tenant_id' => $tenant->id,
                'customer_id' => $customerA->id,
                'po_number' => 'DEMO-PO-1',
                'total_amount' => 1200.0,
                'status' => 'CONFIRMED',
            ]
        );

        SalesOrderItem::firstOrCreate(
            ['sales_order_id' => $order->id, 'product_id' => $product->id],
            [
                'order_qty' => 10,
                'rate' => 120.0,
                'order_value' => 1200.0,
                'shipped_qty' => 10,
                'balance_qty' => 0,
            ]
        );

        $shipment = CommercialShipment::firstOrCreate(
            ['shipment_number' => 'DEMO-SHP-1'],
            [
                'tenant_id' => $tenant->id,
                'sales_order_id' => $order->id,
                'shipment_date' => now(),
                'transporter' => 'Demo Transporter',
                'lr_number' => 'DEMO-LR-1',
                'status' => 'DELIVERED',
            ]
        );

        Invoice::firstOrCreate(
            ['invoice_number' => 'DEMO-INV-1'],
            [
                'tenant_id' => $tenant->id,
                'customer_id' => $customerA->id,
                'sales_order_id' => $order->id,
                'commercial_shipment_id' => $shipment->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'total_amount' => 1200.0,
                'balance_due' => 1200.0,
                'status' => 'ISSUED',
            ]
        );
    }
}
