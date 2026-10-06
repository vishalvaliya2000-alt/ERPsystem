<?php

declare(strict_types=1);

use App\Actions\ProvisionTenantAction;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Firm;
use App\Models\Lead;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CompanyFirmContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['pwacorp'])->delete();
    DB::table('tenants')->whereIn('id', ['pwacorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_pwacorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Surat Textile Processors Ltd',
        'subdomain' => 'pwacorp',
        'owner_name' => 'Dipak Chotai',
        'owner_email' => 'dipak@surattextile.com',
        'password' => 'PwaSecret123!',
        'plan' => 'Standard',
        'gstin' => '24AAACT1234F1Z9',
    ]);

    tenancy()->initialize($this->tenant);
    $this->owner = User::where('email', 'dipak@surattextile.com')->firstOrFail();
    $this->firm = Firm::first();

    $this->customer = Customer::create([
        'customer_code' => 'CUST-PWA-01',
        'company_name' => 'Vardhman Fabrics Pvt Ltd',
        'primary_contact_person' => 'Mohit Jain',
        'primary_email' => 'mohit@vardhman.com',
        'primary_phone' => '+919824033333',
        'address_line' => 'Ring Road, Surat',
        'city' => 'Surat',
        'state' => 'Gujarat',
        'state_code' => 24,
        'pincode' => '395002',
        'gst_number' => '24AAACV3333F1Z1',
        'status' => 'ACTIVE',
    ]);

    $this->product = Product::create([
        'product_name' => 'Super Cotton Grey Fabric 40s',
        'product_code' => 'PRD-TEX-01',
        'hsn_code' => '52081100',
        'unit' => 'MTR',
        'base_price' => 75.00,
        'tax_rate' => 5.00,
        'status' => 'ACTIVE',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    CompanyFirmContext::flushCache();
});

test('pwa web app manifest and service worker are accessible with valid structure', function () {
    // 1. Web App Manifest
    $manifestPath = public_path('manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifestJson = json_decode((string) file_get_contents($manifestPath), true);
    expect($manifestJson)->toBeArray()
        ->and($manifestJson['name'])->toBe('ERPSaaS - Enterprise ERP')
        ->and($manifestJson['short_name'])->toBe('ERPSaaS')
        ->and($manifestJson['display'])->toBe('standalone')
        ->and($manifestJson['theme_color'])->toBe('#091315')
        ->and($manifestJson['background_color'])->toBe('#091315')
        ->and($manifestJson['icons'])->not->toBeEmpty();

    // 2. Service Worker File
    $swPath = public_path('sw.js');
    expect(file_exists($swPath))->toBeTrue();

    $swContent = (string) file_get_contents($swPath);
    expect($swContent)->toContain('erpsaas-cache-v1')
        ->and($swContent)->toContain('/offline')
        ->and($swContent)->toContain("self.addEventListener('fetch'");

    // 3. Offline fallback page
    $response = $this->get('http://pwacorp.localhost/offline');
    $response->assertOk()
        ->assertSee('OFFLINE MODE')
        ->assertSee('Connection Lost')
        ->assertSee('Offline Draft Queue');
});

test('pwa offline draft synchronization api ingests sales orders, leads, and expenses seamlessly', function () {
    $drafts = [
        [
            'id' => 'draft_order_001',
            'type' => 'SALES_ORDER',
            'payload' => [
                'order_number' => 'SO-PWA-OFFLINE-99',
                'customer_id' => $this->customer->id,
                'order_date' => Carbon::now()->format('Y-m-d'),
                'currency' => 'INR',
                'subtotal' => 150000.00,
                'tax_total' => 7500.00,
                'grand_total' => 157500.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2000.00,
                        'rate' => 75.00,
                        'total' => 150000.00,
                    ],
                ],
            ],
        ],
        [
            'id' => 'draft_lead_002',
            'type' => 'LEAD',
            'payload' => [
                'company_name' => 'Apex Textiles Processing Mills',
                'contact_person' => 'Girish Parekh',
                'phone' => '+919825144444',
                'email' => 'girish@apextextiles.com',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'estimated_value' => 500000.00,
            ],
        ],
        [
            'id' => 'draft_expense_003',
            'type' => 'EXPENSE',
            'payload' => [
                'expense_category' => 'Loom Machinery Spares',
                'vendor_invoice_number' => 'INV-LMS-888',
                'base_amount' => 25000.00,
                'gst_rate' => 18.00,
                'is_itc_eligible' => true,
            ],
        ],
    ];

    $response = $this->actingAs($this->owner)
        ->postJson('http://pwacorp.localhost/api/sync/offline-drafts', [
            'drafts' => $drafts,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'synced_count' => 3,
        ]);

    // 1. Verify Sales Order created as DRAFT
    $order = SalesOrder::where('order_number', 'SO-PWA-OFFLINE-99')->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('DRAFT')
        ->and((float) $order->total_amount)->toEqualWithDelta(157500.00, 0.01)
        ->and($order->items)->toHaveCount(1);

    // 2. Verify Lead created
    $lead = Lead::where('company_name', 'Apex Textiles Processing Mills')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->phone)->toBe('+919825144444')
        ->and($lead->source)->toBe('PWA Offline Field App');

    // 3. Verify Expense created
    $expense = Expense::where('vendor_invoice_number', 'INV-LMS-888')->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->base_amount)->toEqualWithDelta(25000.00, 0.01)
        ->and((float) $expense->total_amount)->toEqualWithDelta(29500.00, 0.01)
        ->and($expense->is_itc_eligible)->toBeTrue();
});

test('pwa cross-device synchronization pulse returns live server state and firm telemetry', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('http://pwacorp.localhost/api/sync/pulse');

    $response->assertOk()
        ->assertJsonStructure([
            'server_time',
            'firm_id',
            'firm_name',
            'kpis' => [
                'pending_orders',
                'in_transit_shipments',
                'overdue_invoices',
            ],
            'status',
        ])
        ->assertJson([
            'status' => 'online',
        ]);
});

test('central portal handles marketing landing, subdomain checks, and workspace lookup', function () {
    // End tenancy to make central requests
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // 1. Central Landing Page
    $landing = $this->get('http://localhost/');
    $landing->assertOk()
        ->assertSee('ERPSaaS')
        ->assertSee('Find Workspace');

    // 2. Subdomain check API (taken subdomain)
    $takenCheck = $this->postJson('http://localhost/api/check-subdomain', [
        'subdomain' => 'pwacorp',
    ]);
    $takenCheck->assertOk()
        ->assertJson([
            'available' => false,
        ]);

    // 3. Subdomain check API (available subdomain)
    $availCheck = $this->postJson('http://localhost/api/check-subdomain', [
        'subdomain' => 'uniquesuratbrand',
    ]);
    $availCheck->assertOk()
        ->assertJson([
            'available' => true,
            'subdomain' => 'uniquesuratbrand',
        ]);

    // 4. Workspace Finder with valid tenant handle
    $findResponse = $this->post('http://localhost/find-workspace', [
        'workspace' => 'pwacorp',
    ]);
    $findResponse->assertRedirect();
    expect($findResponse->headers->get('Location'))->toContain('pwacorp.localhost');

    // 5. Workspace Finder with unknown handle returns error
    $notFoundResponse = $this->post('http://localhost/find-workspace', [
        'workspace' => 'nonexistententerprise',
    ]);
    $notFoundResponse->assertSessionHasErrors(['workspace']);
});

test('central super admin portal manages tenant status and performs audited impersonation', function () {
    // End tenancy to access central database
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // 1. Super-Admin Dashboard
    $dashboard = $this->get('http://localhost/super-admin');
    $dashboard->assertOk()
        ->assertSee('Total Tenants')
        ->assertSee('Active Workspaces');

    // 2. Super-Admin Tenants List
    $tenantsList = $this->get('http://localhost/super-admin/tenants?search=pwacorp');
    $tenantsList->assertOk()
        ->assertSee('Surat Textile Processors Ltd')
        ->assertSee('pwacorp');

    // 3. Toggle Status (Suspend Tenant)
    $toggleResponse = $this->post('http://localhost/super-admin/tenants/pwacorp/toggle');
    $toggleResponse->assertRedirect();

    $tenant = Tenant::find('pwacorp');
    expect($tenant->status)->toBe('suspended');

    // Re-activate Tenant
    $this->post('http://localhost/super-admin/tenants/pwacorp/toggle');
    $tenant->refresh();
    expect($tenant->status)->toBe('active');

    // 4. Audited Impersonation
    $impersonateResponse = $this->post('http://localhost/super-admin/tenants/pwacorp/impersonate');
    $impersonateResponse->assertRedirect();

    // Verify impersonation audit log entry created in central database
    $log = DB::table('impersonation_logs')
        ->where('tenant_id', 'pwacorp')
        ->latest('started_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe('pwacorp')
        ->and($log->reason)->toContain('Administrative inspection');

    // Verify redirect URL includes impersonation token
    $redirectUrl = $impersonateResponse->headers->get('Location');
    expect($redirectUrl)->toContain('impersonate=1')
        ->and($redirectUrl)->toContain('tenant_id=pwacorp');

    // 5. Tenant Login consumes impersonation session and signs in as tenant user
    tenancy()->initialize($tenant);
    $tenantLoginResponse = $this->get('http://pwacorp.localhost/login?impersonate=1&tenant_id=pwacorp');
    $tenantLoginResponse->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('dipak@surattextile.com')
        ->and(session('is_impersonating'))->toBeTrue();
});
