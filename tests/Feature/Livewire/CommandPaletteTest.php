<?php

use App\Livewire\Common\CommandPalette;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenantA = Tenant::first() ?? Tenant::create([
        'name' => 'Alpha Industrial Foods',
        'slug' => 'alpha-industrial-foods',
        'industry' => 'Dehydrates',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $this->tenantB = Tenant::where('id', '!=', $this->tenantA->id)->first() ?? Tenant::create([
        'name' => 'Beta Foreign Foods',
        'slug' => 'beta-foreign-foods',
        'industry' => 'Spices',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'BINV-',
        'quotation_prefix' => 'BQTN-',
        'po_prefix' => 'BPO-',
        'shipment_prefix' => 'BSHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->user->tenant_id = $this->tenantA->id;
    $this->user->save();

    TenantManager::setTenant($this->tenantA);
});

test('command palette component renders successfully', function () {
    $this->actingAs($this->user);

    Livewire::test(CommandPalette::class)
        ->assertStatus(200)
        ->call('open')
        ->assertSee('Quick Jump Modules');
});

test('command palette opens and closes on events', function () {
    $this->actingAs($this->user);

    Livewire::test(CommandPalette::class)
        ->assertSet('isOpen', false)
        ->dispatch('open-command-palette')
        ->assertSet('isOpen', true)
        ->dispatch('close-command-palette')
        ->assertSet('isOpen', false);
});

test('command palette searches records and enforces strict multi-tenancy isolation', function () {
    $this->actingAs($this->user);

    // Tenant A Customer
    $custA = Customer::create([
        'tenant_id' => $this->tenantA->id,
        'company_name' => 'Zenith Onion Exporters',
        'primary_contact_person' => 'Zenith Contact',
        'city' => 'Bhavnagar',
        'customer_type' => 'DOMESTIC',
    ]);

    // Tenant B Customer
    $custB = Customer::create([
        'tenant_id' => $this->tenantB->id,
        'company_name' => 'Zenith Global Foreign Foods',
        'primary_contact_person' => 'Foreign Contact',
        'city' => 'Mumbai',
        'customer_type' => 'EXPORT',
    ]);

    // Tenant A Order
    $orderA = SalesOrder::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $custA->id,
        'order_number' => 'SO-ZENITH-999',
        'order_date' => now(),
        'total_amount' => 50000,
        'status' => 'CONFIRMED',
    ]);

    // Tenant B Order
    $orderB = SalesOrder::create([
        'tenant_id' => $this->tenantB->id,
        'customer_id' => $custB->id,
        'order_number' => 'SO-ZENITH-000',
        'order_date' => now(),
        'total_amount' => 90000,
        'status' => 'CONFIRMED',
    ]);

    TenantManager::setTenant($this->tenantA);

    Livewire::test(CommandPalette::class)
        ->call('open')
        ->set('query', 'Zenith')
        ->assertSee('Zenith Onion Exporters')
        ->assertSee('SO-ZENITH-999')
        ->assertDontSee('Zenith Global Foreign Foods')
        ->assertDontSee('SO-ZENITH-000');
});
