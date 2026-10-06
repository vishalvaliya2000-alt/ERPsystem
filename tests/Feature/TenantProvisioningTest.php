<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Firm;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    DB::table('domains')->whereIn('tenant_id', ['kisanagro', 'alphaagro', 'betaauth'])->delete();
    DB::table('tenants')->whereIn('id', ['kisanagro', 'alphaagro', 'betaauth'])->delete();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    // Clean up any test tenant databases
    try {
        $testDbs = DB::select("SHOW DATABASES LIKE 'saas_erp_test_tenant_%'");
        foreach ($testDbs as $dbObj) {
            $prop = array_keys((array) $dbObj)[0];
            $dbName = $dbObj->$prop;
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        }
        DB::table('domains')->whereIn('tenant_id', ['kisanagro', 'alphaagro', 'betaauth'])->delete();
        DB::table('tenants')->whereIn('id', ['kisanagro', 'alphaagro', 'betaauth'])->delete();
    } catch (Throwable $e) {
        // Ignore in cleanup
    }
});

test('central landing page is accessible on central domain', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('The Modern ERP Built for');
    $response->assertSee('Indian Manufacturers');
});

test('subdomain availability API correctly validates format, reserved handles, and uniqueness', function () {
    // 1. Valid and available
    $res1 = $this->postJson('/api/check-subdomain', ['subdomain' => 'validaero']);
    $res1->assertStatus(200);
    $res1->assertJson(['available' => true]);

    // 2. Reserved handle
    $res2 = $this->postJson('/api/check-subdomain', ['subdomain' => 'admin']);
    $res2->assertStatus(200);
    $res2->assertJson(['available' => false]);
    expect($res2->json('message'))->toContain('reserved');

    // 3. Invalid characters
    $res3 = $this->postJson('/api/check-subdomain', ['subdomain' => 'inv@lid_name']);
    $res3->assertStatus(200);
    $res3->assertJson(['available' => false]);
});

test('tenant registration provisions dedicated MySQL database and seeds all defaults', function () {
    $payload = [
        'business_name' => 'Kisan Agro Industries',
        'subdomain' => 'kisanagro',
        'owner_name' => 'Ramesh Patel',
        'email' => 'ramesh@kisanagro.com',
        'phone' => '9825012345',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'gstin' => '24AAACK1234F1Z9',
        'plan' => 'Standard',
        'terms' => 'on',
    ];

    $response = $this->post('/register', $payload);

    // Assert redirection to tenant subdomain login
    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('kisanagro.localhost');
    expect($response->headers->get('Location'))->toContain('/login?registered=1');

    // Assert central tenant record created
    $tenant = Tenant::find('kisanagro');
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('Kisan Agro Industries');
    expect($tenant->owner_email)->toBe('ramesh@kisanagro.com');

    // Assert dedicated database exists in MySQL
    $dbName = 'saas_erp_test_tenant_kisanagro';
    $dbExists = DB::select("SHOW DATABASES LIKE '{$dbName}'");
    expect($dbExists)->not->toBeEmpty();

    // Assert all tenant seeders executed in tenant database
    $tenant->run(function () {
        // 1. Departments
        expect(Department::count())->toBe(4);
        expect(Department::where('code', 'SALES')->exists())->toBeTrue();
        expect(Department::where('code', 'ACC')->exists())->toBeTrue();
        expect(Department::where('code', 'QC')->exists())->toBeTrue();
        expect(Department::where('code', 'DISPATCH')->exists())->toBeTrue();

        // 2. Roles
        expect(Role::where('name', 'owner')->exists())->toBeTrue();
        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::where('name', 'department_manager')->exists())->toBeTrue();

        // 3. Default Company & Firm
        $company = Company::where('legal_name', 'Kisan Agro Industries')->first();
        expect($company)->not->toBeNull();

        $firm = Firm::where('company_id', $company->id)->first();
        expect($firm)->not->toBeNull();
        expect($firm->gstin)->toBe('24AAACK1234F1Z9');

        // 4. Chart of Accounts
        expect(DB::table('chart_of_accounts')->count())->toBeGreaterThan(10);

        // 5. Owner User
        $owner = User::where('email', 'ramesh@kisanagro.com')->first();
        expect($owner)->not->toBeNull();
        expect($owner->name)->toBe('Ramesh Patel');
        expect($owner->hasRole('owner'))->toBeTrue();
        expect(Hash::check('SecurePass123!', $owner->password))->toBeTrue();
    });
});
