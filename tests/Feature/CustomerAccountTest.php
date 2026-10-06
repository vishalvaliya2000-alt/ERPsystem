<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;

beforeEach(function () {
    $this->tenant = Tenant::firstOrCreate(
        ['slug' => 'test-customer-tenant'],
        ['name' => 'Customer Test Tenant']
    );

    $this->user = User::firstOrCreate(
        ['email' => 'test_client_mgr@example.com'],
        [
            'name' => 'Client Account Tester',
            'password' => bcrypt('password123'),
        ]
    );

    TenantManager::setTenant($this->tenant);
});

test('Customer::generateNextCode generates sequential zero-padded CUST-xxxx codes', function () {
    $initialCode = Customer::generateNextCode($this->tenant->id);
    expect($initialCode)->toMatch('/^CUST-\d{4}$/');

    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Sequential Test Client 1',
        'customer_code' => $initialCode,
    ]);

    $nextCode = Customer::generateNextCode($this->tenant->id);
    expect($nextCode)->toMatch('/^CUST-\d{4}$/');

    preg_match('/CUST-(\d+)/', $initialCode, $m1);
    preg_match('/CUST-(\d+)/', $nextCode, $m2);
    expect((int) $m2[1])->toBeGreaterThan((int) $m1[1]);
});

test('customer creation stores Legal Name and Trade Name and auto-generates Customer Code when omitted', function () {
    $response = $this->actingAs($this->user)->post(route('customers.store'), [
        'customer_code' => '',
        'company_name' => 'Balaji Agro Industries Pvt Ltd',
        'trade_name' => 'Balaji Agro Brand',
        'gst_number' => '24AAACB'.rand(1000, 9999).'F1Z5',
        'primary_contact_person' => 'Bhavesh Patel',
        'primary_phone' => '9898012345',
        'city' => 'Mahuva',
        'state' => 'Gujarat',
        'payment_terms_days' => 30,
    ]);

    $created = Customer::where('company_name', 'Balaji Agro Industries Pvt Ltd')->first();
    expect($created)->not->toBeNull();
    expect($created->trade_name)->toBe('Balaji Agro Brand');
    expect($created->customer_code)->toStartWith('CUST-');
    expect($created->legal_name)->toBe('Balaji Agro Industries Pvt Ltd');

    $response->assertRedirect(route('customers.show', $created->id));
});

test('customer creation accepts legal_name attribute directly', function () {
    $response = $this->actingAs($this->user)->post(route('customers.store'), [
        'legal_name' => 'Kisan Food Processors LLP',
        'trade_name' => 'Kisan Pure Foods',
        'gst_number' => '24AAACK'.rand(1000, 9999).'F1Z6',
        'city' => 'Bhavnagar',
    ]);

    $created = Customer::where('company_name', 'Kisan Food Processors LLP')->first();
    expect($created)->not->toBeNull();
    expect($created->trade_name)->toBe('Kisan Pure Foods');
    expect($created->customer_code)->toStartWith('CUST-');
    $response->assertRedirect(route('customers.show', $created->id));
});

test('customers index displays Legal Name, Trade Name and auto-generated customer code in modal', function () {
    $cust = Customer::create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Maruti Dehydration Foods Pvt Ltd',
        'trade_name' => 'Maruti Foods',
        'customer_code' => 'CUST-8899',
    ]);

    $response = $this->actingAs($this->user)->get(route('customers.index'));
    $response->assertStatus(200);
    $response->assertSee('Maruti Dehydration Foods Pvt Ltd');
    $response->assertSee('Maruti Foods');
    $response->assertSee('Legal Name of Business');
    $response->assertSee('Trade Name');
    $response->assertSee('Auto-Generated');
    $response->assertViewHas('nextCustomerCode');
});

test('executive dashboard does NOT render Documents Requiring Attention card', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));
    $response->assertStatus(200);
    $response->assertDontSee('Documents Requiring Attention');
});

test('every new tenant starts customer code sequence from CUST-0001 independently', function () {
    $tenantA = Tenant::create([
        'name' => 'Alpha Agro Exports',
        'slug' => 'alpha-agro-'.rand(1000, 9999),
    ]);

    $tenantB = Tenant::create([
        'name' => 'Beta Spices Global',
        'slug' => 'beta-spices-'.rand(1000, 9999),
    ]);

    // For Tenant A: First customer must be CUST-0001
    TenantManager::setTenant($tenantA);
    $codeA1 = Customer::generateNextCode($tenantA->id);
    expect($codeA1)->toBe('CUST-0001');

    $custA1 = Customer::create([
        'tenant_id' => $tenantA->id,
        'company_name' => 'Tenant A First Customer',
        'customer_code' => $codeA1,
    ]);
    expect($custA1->customer_code)->toBe('CUST-0001');

    $codeA2 = Customer::generateNextCode($tenantA->id);
    expect($codeA2)->toBe('CUST-0002');

    // For Tenant B (brand new tenant): Must also start from CUST-0001
    TenantManager::setTenant($tenantB);
    $codeB1 = Customer::generateNextCode($tenantB->id);
    expect($codeB1)->toBe('CUST-0001');

    // Creating customer for Tenant B with auto-generation should generate CUST-0001
    $custB1 = Customer::create([
        'tenant_id' => $tenantB->id,
        'company_name' => 'Tenant B First Customer',
        // customer_code omitted to trigger booted auto-generation
    ]);
    expect($custB1->customer_code)->toBe('CUST-0001');

    // Tenant B's next customer is CUST-0002
    $codeB2 = Customer::generateNextCode($tenantB->id);
    expect($codeB2)->toBe('CUST-0002');

    // Verify both tenants possess a CUST-0001 record in the database simultaneously
    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('customer_code', 'CUST-0001')->exists())->toBeTrue();
    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('customer_code', 'CUST-0001')->exists())->toBeTrue();
});
