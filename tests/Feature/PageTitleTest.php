<?php

use App\Models\Tenant;
use App\Models\User;

test('guest pages contain descriptive <title> tags', function () {
    $loginResponse = $this->get(route('login'));
    $loginResponse->assertStatus(200);
    $loginResponse->assertSee('Sign In — '.config('app.name', 'Vyapar ERP'));

    $registerResponse = $this->get(route('register'));
    $registerResponse->assertStatus(200);
    $registerResponse->assertSee('Register Your Business — '.config('app.name', 'Vyapar ERP'));
});

test('authenticated dashboard and primary navigation pages render their specific <title> tags', function () {
    $tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Global Tenant',
        'slug' => 'acme-global-tenant',
        'industry' => 'Food Processing & Exports',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $user = User::first() ?? User::factory()->create(['tenant_id' => $tenant->id]);
    if (! $user->tenant_id) {
        $user->tenant_id = $tenant->id;
        $user->save();
    }

    $routes = [
        ['url' => '/', 'title' => "Today's Actions & Command Center"],
        ['url' => '/customers', 'title' => 'Customers 360 & Ledger Directory'],
        ['url' => '/pipeline', 'title' => 'Leads & Sales Pipeline'],
        ['url' => '/orders', 'title' => 'Sales Orders & Contract Revisions'],
        ['url' => '/shipments', 'title' => 'Commercial Shipments & LR Tracking'],
        ['url' => '/invoices', 'title' => 'GST Invoices & Payment Ledger'],
        ['url' => '/products', 'title' => 'Product Catalog & HSN Rates'],
        ['url' => '/quotations', 'title' => 'Quotations & Estimates'],
        ['url' => '/samples', 'title' => 'Sample Dispatch & Courier Tracking'],
        ['url' => '/vendors', 'title' => 'Vendors & Mandi Purchase Registry'],
        ['url' => '/transporters', 'title' => 'Transporters & Logistics Directory'],
        ['url' => '/excel', 'title' => 'Excel Reports & Export'],
        ['url' => '/profile', 'title' => 'My Account & Security (2FA & RBAC)'],
        ['url' => '/organization/settings', 'title' => 'Company Profile & Invoice Settings'],
        ['url' => '/organization/team', 'title' => 'Team Management & Spatie RBAC'],
    ];

    foreach ($routes as $item) {
        $response = $this->actingAs($user)->get($item['url']);
        $response->assertStatus(200);
        $response->assertSee($item['title']);
    }
});
