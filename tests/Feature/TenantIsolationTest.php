<?php

use App\Actions\ProvisionTenantAction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    DB::table('domains')->whereIn('tenant_id', ['alphaagro', 'betaauth', 'depttenant'])->delete();
    DB::table('tenants')->whereIn('id', ['alphaagro', 'betaauth', 'depttenant'])->delete();
    auth()->logout();
    session()->flush();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    auth()->logout();
    session()->flush();

    try {
        $testDbs = DB::select("SHOW DATABASES LIKE 'saas_erp_test_tenant_%'");
        foreach ($testDbs as $dbObj) {
            $prop = array_keys((array) $dbObj)[0];
            $dbName = $dbObj->$prop;
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        }
        DB::table('domains')->whereIn('tenant_id', ['alphaagro', 'betaauth', 'depttenant'])->delete();
        DB::table('tenants')->whereIn('id', ['alphaagro', 'betaauth', 'depttenant'])->delete();
    } catch (Throwable $e) {
        // Ignore
    }
});

test('cross-tenant data leakage is strictly impossible across separate databases', function () {
    $provisioner = app(ProvisionTenantAction::class);

    // 1. Provision Tenant Alpha
    $tenantAlpha = $provisioner->execute([
        'name' => 'Alpha Processors Ltd',
        'subdomain' => 'alpha',
        'owner_name' => 'Alice Alpha',
        'owner_email' => 'alice@alpha.com',
        'password' => 'AlphaPass123!',
    ]);

    // 2. Provision Tenant Beta
    $tenantBeta = $provisioner->execute([
        'name' => 'Beta Exporters Ltd',
        'subdomain' => 'beta',
        'owner_name' => 'Bob Beta',
        'owner_email' => 'bob@beta.com',
        'password' => 'BetaPass123!',
    ]);

    // 3. Insert specific customer and product inside Tenant Alpha
    $tenantAlpha->run(function () {
        Customer::create([
            'customer_code' => 'CUST-ALPHA-001',
            'company_name' => 'Exclusive Alpha Client Ltd',
            'status' => 'Active',
        ]);

        Product::create([
            'product_code' => 'PROD-ALPHA-WHITE-ONION',
            'product_name' => 'Alpha White Onion Flakes Premium',
            'standard_rate' => 350.00,
        ]);

        expect(Customer::where('customer_code', 'CUST-ALPHA-001')->exists())->toBeTrue();
        expect(Product::where('product_code', 'PROD-ALPHA-WHITE-ONION')->exists())->toBeTrue();
    });

    // 4. Switch to Tenant Beta and verify Alpha records DO NOT exist
    $tenantBeta->run(function () {
        // In Beta, Alpha's customer and product must NOT exist
        expect(Customer::where('customer_code', 'CUST-ALPHA-001')->exists())->toBeFalse();
        expect(Product::where('product_code', 'PROD-ALPHA-WHITE-ONION')->exists())->toBeFalse();

        // Create Beta-specific customer
        Customer::create([
            'customer_code' => 'CUST-BETA-001',
            'company_name' => 'Beta Global Importers LLC',
            'status' => 'Active',
        ]);

        expect(Customer::where('customer_code', 'CUST-BETA-001')->exists())->toBeTrue();
    });

    // 5. Switch back to Tenant Alpha and verify Beta record DOES NOT exist
    $tenantAlpha->run(function () {
        expect(Customer::where('customer_code', 'CUST-BETA-001')->exists())->toBeFalse();
    });
});

test('users can only authenticate against their own tenant database', function () {
    $provisioner = app(ProvisionTenantAction::class);

    // Provision Tenant Alpha
    $tenantAlpha = $provisioner->execute([
        'name' => 'Alpha Factory',
        'subdomain' => 'alphaauth',
        'owner_name' => 'Alice Alpha',
        'owner_email' => 'alice@alpha.com',
        'password' => 'AlphaPass123!',
    ]);

    // Provision Tenant Beta
    $tenantBeta = $provisioner->execute([
        'name' => 'Beta Factory',
        'subdomain' => 'betaauth',
        'owner_name' => 'Bob Beta',
        'owner_email' => 'bob@beta.com',
        'password' => 'BetaPass123!',
    ]);

    // Attempt to authenticate Alice on Beta's domain -> MUST FAIL
    $response = $this->post('http://betaauth.localhost/login', [
        'email' => 'alice@alpha.com',
        'password' => 'AlphaPass123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();

    // Authenticate Bob on Beta's domain -> MUST SUCCEED
    $responseBob = $this->post('http://betaauth.localhost/login', [
        'email' => 'bob@beta.com',
        'password' => 'BetaPass123!',
    ]);

    $responseBob->assertRedirect('http://betaauth.localhost');
    $this->assertAuthenticated();

    // Verify authenticated user is Bob
    expect(auth()->user()->email)->toBe('bob@beta.com');
});

test('unknown subdomain returns friendly 404 workspace not found page', function () {
    $response = $this->get('http://nonexistentworkspace.localhost/login');

    $response->assertStatus(404);
    $response->assertSee('No Workspace Located');
    $response->assertSee('nonexistentworkspace');
});

test('workspace finder correctly resolves and redirects to tenant login', function () {
    $provisioner = app(ProvisionTenantAction::class);

    $provisioner->execute([
        'name' => 'FindMe Exports',
        'subdomain' => 'findme',
        'owner_name' => 'Target User',
        'owner_email' => 'target@findme.com',
        'password' => 'Secret123!',
    ]);

    // Search by subdomain
    $response = $this->post('/find-workspace', ['workspace' => 'findme']);
    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('findme.localhost');
    expect($response->headers->get('Location'))->toContain('/login');

    // Search by email
    $responseEmail = $this->post('/find-workspace', ['workspace' => 'target@findme.com']);
    $responseEmail->assertStatus(302);
    expect($responseEmail->headers->get('Location'))->toContain('findme.localhost');
    expect($responseEmail->headers->get('Location'))->toContain('/login');

    // Search for unknown
    $responseUnknown = $this->post('/find-workspace', ['workspace' => 'unknowncorp']);
    $responseUnknown->assertSessionHasErrors('workspace');
});
