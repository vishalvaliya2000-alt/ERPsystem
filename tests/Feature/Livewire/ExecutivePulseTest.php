<?php

use App\Livewire\Dashboard\ExecutivePulse;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Pulse Test Food Corp',
        'slug' => 'pulse-test-food-corp',
        'industry' => 'Food Processing',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'PINV-',
        'quotation_prefix' => 'PQTN-',
        'po_prefix' => 'PPO-',
        'shipment_prefix' => 'PSHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->tenant_id = $this->tenant->id;
    $this->user->save();

    TenantManager::setTenant($this->tenant);
});

test('executive pulse component renders successfully with 6 core KPI metrics', function () {
    $this->actingAs($this->user);

    Livewire::test(ExecutivePulse::class)
        ->assertStatus(200)
        ->assertSee('Total Sales')
        ->assertSee('This Month')
        ->assertSee('Outstanding AR')
        ->assertSee('Collections')
        ->assertSee('Sales Orders')
        ->assertSee('Active Deals');
});

test('executive pulse component responds to period filter changes', function () {
    $this->actingAs($this->user);

    Livewire::test(ExecutivePulse::class)
        ->assertSet('period', 'this_fy')
        ->call('setPeriod', 'this_month')
        ->assertSet('period', 'this_month')
        ->assertDispatched('analytics-filter-changed')
        ->call('setPeriod', 'all')
        ->assertSet('period', 'all')
        ->call('resetFilters')
        ->assertSet('period', 'this_fy');
});

test('executive pulse filters by customer and updates reactive state', function () {
    $this->actingAs($this->user);

    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Specific Pulse Buyer Ltd',
        'customer_type' => 'DOMESTIC',
    ]);

    Livewire::test(ExecutivePulse::class)
        ->set('selectedCustomer', (string) $customer->id)
        ->assertSet('selectedCustomer', (string) $customer->id)
        ->assertSee('Filtered Client');
});
