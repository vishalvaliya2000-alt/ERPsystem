<?php

use App\Models\BusinessDocument;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Document Tenant',
        'slug' => 'acme-document-tenant',
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
    Storage::fake('public');
});

test('documents route redirects to dashboard since document centre page is removed', function () {
    $response = $this->actingAs($this->user)->get(route('documents.index'));

    $response->assertRedirect(route('dashboard'));
});

test('duplicate check endpoint detects duplicate document numbers for a customer', function () {
    $customer = Customer::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'company_name' => 'Monk Food Specialties'],
        ['contact_person' => 'Amit Shah', 'email' => 'amit@monkfood.test', 'status' => 'ACTIVE']
    );

    BusinessDocument::create([
        'tenant_id' => $this->tenant->id,
        'document_type' => 'PO',
        'document_number' => 'TEST-PO-DUP-999',
        'customer_id' => $customer->id,
        'total_amount' => 50000,
        'status' => 'ACTIVE',
        'current_version' => 1,
    ]);

    // Test existing duplicate
    $response = $this->actingAs($this->user)->getJson(route('documents.check-duplicate', [
        'document_number' => 'TEST-PO-DUP-999',
        'customer_id' => $customer->id,
        'document_type' => 'PO',
    ]));

    $response->assertStatus(200);
    $response->assertJson([
        'is_duplicate' => true,
        'document_number' => 'TEST-PO-DUP-999',
    ]);

    // Test non-existing number
    $responseClean = $this->actingAs($this->user)->getJson(route('documents.check-duplicate', [
        'document_number' => 'UNIQUE-PO-000000001',
        'customer_id' => $customer->id,
        'document_type' => 'PO',
    ]));

    $responseClean->assertStatus(200);
    $responseClean->assertJson([
        'is_duplicate' => false,
        'document_number' => 'UNIQUE-PO-000000001',
    ]);
});

test('document show endpoint returns document details, active version, and connected chain', function () {
    $customer = Customer::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'company_name' => 'VLC Spices & Extracts'],
        ['contact_person' => 'Kiran Varma', 'email' => 'kiran@vlc.test', 'status' => 'ACTIVE']
    );

    $doc = BusinessDocument::create([
        'tenant_id' => $this->tenant->id,
        'document_type' => 'PO',
        'document_number' => 'TEST-PO-SHOW-101',
        'customer_id' => $customer->id,
        'total_amount' => 125000,
        'status' => 'ACTIVE',
        'current_version' => 1,
    ]);

    DocumentVersion::create([
        'document_id' => $doc->id,
        'version_number' => 1,
        'file_path' => 'documents/'.$this->tenant->id.'/test.pdf',
        'file_name' => 'test_po.pdf',
        'file_size' => 20480,
        'mime_type' => 'application/pdf',
        'uploaded_by_user_id' => $this->user->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('documents.show', $doc->id));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'document' => [
            'id',
            'document_type',
            'document_number',
            'total_amount',
            'connected_chain',
            'versions',
        ],
    ]);
});

test('customer show page includes documents tab and active version', function () {
    $customer = Customer::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'company_name' => 'Monk Food Specialties'],
        ['contact_person' => 'Amit Shah', 'email' => 'amit@monkfood.test', 'status' => 'ACTIVE']
    );

    $response = $this->actingAs($this->user)->get(route('customers.show', $customer->id));

    $response->assertStatus(200);
    $response->assertSee('Documents');
});

test('dashboard renders Document Intelligence metrics and does not render removed Documents Requiring Attention radar', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertDontSee('Documents Requiring Attention');
    $response->assertSee('POs Received');
    $response->assertSee('Invoices Issued');
    $response->assertSee('Uninvoiced Dispatches');
    $response->assertViewHas('documentMetrics');
    $response->assertViewHas('documentsAttention');
});
