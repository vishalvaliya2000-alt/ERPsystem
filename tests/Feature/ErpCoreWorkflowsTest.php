<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sample;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Operations Tenant',
        'slug' => 'acme-operations-tenant',
        'industry' => 'Food Processing & Exports',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    if (! $this->user->tenant_id) {
        $this->user->tenant_id = $this->tenant->id;
        $this->user->save();
    }

    TenantManager::setTenant($this->tenant);
});

test('dashboard renders with ERPSaaS bento metrics and cockpit briefing', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));
    $response->assertStatus(200);
    $response->assertSee('Collections Pulse');
    $response->assertSee('Factory Velocity');
    $response->assertSee('Commercial');
    $response->assertSee('Executive War Room');
});

test('customer model auto-generates customer_code when omitted', function () {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Automated Pest Test Client '.rand(100, 999),
        'city' => 'Mahuva',
        'state' => 'Gujarat',
    ]);

    expect($customer->customer_code)->not->toBeEmpty();
    expect($customer->customer_code)->toStartWith('CUST-');
});

test('sample status update endpoint functions properly via route samples.status', function () {
    $product = Product::first() ?? Product::create([
        'tenant_id' => $this->tenant->id,
        'product_code' => 'PRD-TEST-'.rand(100, 999),
        'product_name' => 'White Onion Flakes A-Grade',
        'standard_rate' => 180,
    ]);

    $sample = Sample::create([
        'tenant_id' => $this->tenant->id,
        'sample_number' => 'SMP-TEST-'.rand(100, 999),
        'product_id' => $product->id,
        'quantity' => 200,
        'delivery_status' => 'IN_TRANSIT',
        'trial_status' => 'Pending',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('samples.status', $sample->id), [
            'delivery_status' => 'DELIVERED',
            'trial_status' => 'In Trial',
            'customer_feedback' => 'Sample received in moisture-proof packaging.',
        ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $sample->refresh();
    expect($sample->delivery_status)->toBe('DELIVERED');
    expect($sample->trial_status)->toBe('In Trial');
});

test('AI assistant query endpoint returns structured answers', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('assistant.query'), [
            'query' => 'How many pending invoices do we have?',
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['title', 'answer']);
});

test('global search API returns matches for query', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'Test']));

    $response->assertStatus(200);
});

test('pipeline page renders 6-stage funnel ribbon and add lead action', function () {
    $response = $this->actingAs($this->user)->get(route('pipeline.index'));
    $response->assertStatus(200);
    $response->assertSee('Lead / Prospect');
    $response->assertSee('Add Lead / Prospect');
    $response->assertSee('All Stages');
    $response->assertSee('Total Deal Flow');
    $response->assertSee('New Inquiries &amp; RFQs', false);
    $response->assertSee('Sample Trials &amp; Lab COA', false);
    $response->assertSee('Rate Quotation &amp; Proforma', false);
    $response->assertSee('Commercial Negotiation');
    $response->assertSee('Closed Won &amp; Customer', false);
});

test('invoices page renders 11-column ERP data table and AR ledger KPIs', function () {
    $response = $this->actingAs($this->user)->get(route('invoices.index'));
    $response->assertStatus(200);
    $response->assertSee('Invoices & AR Ledger', false);
    $response->assertSee('Total Invoiced');
    $response->assertSee('Total Collections');
    $response->assertSee('Balance Outstanding');
    $response->assertSee('Generate Invoice');
    $response->assertSee('Payment Receipt');
    $response->assertSee('Invoice No.');
    $response->assertSee('Customer');
    $response->assertSee('Linked PO');
    $response->assertSee('Shipment / LR');
    $response->assertSee('Invoice Amount');
    $response->assertSee('Received');
    $response->assertSee('Balance Due');
    $response->assertSee('Due Date & Aging', false);
    $response->assertSee('Payment Receipts & Bank Settlements', false);
});

test('products page renders edit action and edit modal', function () {
    $page = $this->actingAs($this->user)->get(route('products.index'));
    $page->assertStatus(200);
    $page->assertSee('Edit Product Master');
    $page->assertSee('openEditModal');
    $page->assertSee('isEditModalOpen');
});

test('different tenants can independently create products and categories with identical names and slugs without unique constraint violation', function () {
    $tenantA = Tenant::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha']);
    $tenantB = Tenant::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta']);

    // Tenant A creates Onion Products
    TenantManager::setTenant($tenantA);
    $catA = ProductCategory::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Onion Products',
        'slug' => 'onion',
    ]);
    $prodA = Product::create([
        'tenant_id' => $tenantA->id,
        'category_id' => $catA->id,
        'product_code' => 'ONION-01',
        'product_name' => 'Dehydrated White Onion Flakes',
        'standard_rate' => 190.0,
    ]);

    // Tenant B creates identical category and SKU independently
    TenantManager::setTenant($tenantB);
    $catB = ProductCategory::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Onion Products',
        'slug' => 'onion',
    ]);
    $prodB = Product::create([
        'tenant_id' => $tenantB->id,
        'category_id' => $catB->id,
        'product_code' => 'ONION-01',
        'product_name' => 'Dehydrated White Onion Flakes',
        'standard_rate' => 205.0,
    ]);

    expect($catA->id)->not->toBe($catB->id);
    expect($prodA->id)->not->toBe($prodB->id);
    expect($catA->tenant_id)->toBe($tenantA->id);
    expect($catB->tenant_id)->toBe($tenantB->id);
});
