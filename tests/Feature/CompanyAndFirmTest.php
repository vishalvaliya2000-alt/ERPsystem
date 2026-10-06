<?php

use App\Actions\ProvisionTenantAction;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Services\CompanyFirmContext;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['multifirmcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['multifirmcorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    // Clean up DB if exists
    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_multifirmcorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Multi-Firm Industrial Holdings Ltd',
        'subdomain' => 'multifirmcorp',
        'owner_name' => 'Harish Patel',
        'owner_email' => 'harish@multifirmcorp.com',
        'password' => 'OwnerPassword123!',
        'plan' => 'Standard',
        'gstin' => '24AAACK9999F1Z5',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        $testDbs = DB::select("SHOW DATABASES LIKE 'saas_erp_test_tenant_%'");
        foreach ($testDbs as $dbObj) {
            $prop = array_keys((array) $dbObj)[0];
            $dbName = $dbObj->$prop;
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        }
        DB::table('domains')->whereIn('tenant_id', ['multifirmcorp'])->delete();
        DB::table('tenants')->whereIn('id', ['multifirmcorp'])->delete();
    } catch (Throwable $e) {
        // Ignore
    }
});

test('default company and primary firm are created and marked default on provisioning', function () {
    $this->tenant->run(function () {
        $companies = Company::all();
        expect($companies)->toHaveCount(1);

        $defaultCompany = $companies->first();
        expect($defaultCompany->is_default)->toBeTrue();
        expect($defaultCompany->is_active)->toBeTrue();
        expect($defaultCompany->legal_name)->toBe('Multi-Firm Industrial Holdings Ltd');

        $firms = Firm::where('company_id', $defaultCompany->id)->get();
        expect($firms)->toHaveCount(1);

        $defaultFirm = $firms->first();
        expect($defaultFirm->is_default)->toBeTrue();
        expect($defaultFirm->is_active)->toBeTrue();
        expect($defaultFirm->state_code)->toBe('24');
        expect($defaultFirm->invoice_prefix)->toBe('INV');
        expect($defaultFirm->quotation_prefix)->toBe('QUO');
    });
});

test('context service resolves default firm and company when no session key is present', function () {
    $this->tenant->run(function () {
        session()->flush();
        CompanyFirmContext::flushCache();

        $resolvedFirm = CompanyFirmContext::getFirm();
        expect($resolvedFirm)->not->toBeNull();
        expect($resolvedFirm->is_default)->toBeTrue();

        $resolvedCompany = CompanyFirmContext::getCompany();
        expect($resolvedCompany)->not->toBeNull();
        expect($resolvedCompany->id)->toBe($resolvedFirm->company_id);

        expect(session('active_firm_id'))->toBe($resolvedFirm->id);
        expect(session('active_company_id'))->toBe($resolvedCompany->id);
    });
});

test('users can create multiple companies and multiple firms with distinct prefixes and GSTINs', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://multifirmcorp.localhost';

    // 1. Create a second legal company
    $createCompanyRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/companies", [
            'legal_name' => 'Saurashtra Dehydrates Private Limited',
            'trade_name' => 'Saurashtra Spices',
            'cin' => 'U01111GJ2022PTC123456',
            'pan' => 'AABCS1234F',
            'website' => 'https://saurashtraspices.com',
        ]);

    $createCompanyRes->assertStatus(302);
    $createCompanyRes->assertSessionHas('success');

    $this->tenant->run(function () {
        $secondCompany = Company::where('legal_name', 'Saurashtra Dehydrates Private Limited')->first();
        expect($secondCompany)->not->toBeNull();
        expect($secondCompany->trade_name)->toBe('Saurashtra Spices');
        expect($secondCompany->pan)->toBe('AABCS1234F');
        expect($secondCompany->is_default)->toBeFalse(); // First company remains default

        // Verify primary unit was automatically generated for this company
        $unit = Firm::where('company_id', $secondCompany->id)->first();
        expect($unit)->not->toBeNull();
        expect($unit->firm_name)->toContain('Saurashtra Spices (Main Unit)');

        // Verify activity log recorded company creation
        $compActivity = Activity::where('log_name', 'companies')->latest('id')->first();
        expect($compActivity)->not->toBeNull();
        expect($compActivity->description)->toContain('Created company Saurashtra Dehydrates Private Limited');
    });

    // 2. Add an additional firm / branch under the new company
    $secondCompanyId = null;
    $this->tenant->run(function () use (&$secondCompanyId) {
        $secondCompanyId = Company::where('legal_name', 'Saurashtra Dehydrates Private Limited')->value('id');
    });

    $createFirmRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/companies/{$secondCompanyId}/firms", [
            'firm_name' => 'Mahuva Processing Unit 2',
            'gstin' => '24AABCS1234F1Z1',
            'state' => 'Gujarat',
            'state_code' => '24',
            'pan' => 'AABCS1234F',
            'address_line1' => 'Plot 45, GIDC Phase 2',
            'city' => 'Mahuva',
            'pincode' => '364290',
            'invoice_prefix' => 'SDP-INV',
            'quotation_prefix' => 'SDP-QUO',
            'po_prefix' => 'SDP-PO',
            'shipment_prefix' => 'SDP-SHP',
            'bank_name' => 'Axis Bank',
            'bank_account_number' => '920020011223344',
            'bank_ifsc' => 'UTIB0000456',
            'bank_branch' => 'Mahuva Main',
        ]);

    $createFirmRes->assertStatus(302);
    $createFirmRes->assertSessionHas('success');

    $this->tenant->run(function () {
        $newFirm = Firm::where('firm_name', 'Mahuva Processing Unit 2')->first();
        expect($newFirm)->not->toBeNull();
        expect($newFirm->gstin)->toBe('24AABCS1234F1Z1');
        expect($newFirm->invoice_prefix)->toBe('SDP-INV');
        expect($newFirm->bank_name)->toBe('Axis Bank');
        expect($newFirm->bank_account_number)->toBe('920020011223344');

        // Verify activity log recorded firm creation
        $firmActivity = Activity::where('log_name', 'firms')->latest('id')->first();
        expect($firmActivity)->not->toBeNull();
        expect($firmActivity->description)->toContain('Added branch/firm Mahuva Processing Unit 2');
    });
});

test('context switcher seamlessly updates active firm and company session', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://multifirmcorp.localhost';

    // Create a secondary firm
    $secondaryFirmId = null;
    $this->tenant->run(function () use (&$secondaryFirmId) {
        $defaultComp = Company::where('is_default', true)->first();
        $secondaryFirm = Firm::create([
            'company_id' => $defaultComp->id,
            'firm_name' => 'Ahmedabad Export Hub',
            'gstin' => '24AAACK9999F1Z6',
            'state' => 'Gujarat',
            'state_code' => '24',
            'invoice_prefix' => 'AHM-INV',
            'quotation_prefix' => 'AHM-QUO',
            'po_prefix' => 'AHM-PO',
            'shipment_prefix' => 'AHM-SHP',
            'is_default' => false,
            'is_active' => true,
        ]);
        $secondaryFirmId = $secondaryFirm->id;
    });

    // Post to context switcher
    $switchRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/switch-context", [
            'firm_id' => $secondaryFirmId,
        ]);

    $switchRes->assertStatus(302);
    $switchRes->assertSessionHas('success');
    expect(session('active_firm_id'))->toBe($secondaryFirmId);

    $this->tenant->run(function () use ($secondaryFirmId) {
        CompanyFirmContext::flushCache();
        $firm = CompanyFirmContext::getFirm();
        expect($firm->id)->toBe($secondaryFirmId);
        expect($firm->firm_name)->toBe('Ahmedabad Export Hub');
        expect($firm->invoice_prefix)->toBe('AHM-INV');
    });
});

test('system blocks deactivating the sole active company or sole active firm', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://multifirmcorp.localhost';

    $companyId = null;
    $firmId = null;
    $this->tenant->run(function () use (&$companyId, &$firmId) {
        $companyId = Company::where('is_active', true)->value('id');
        $firmId = Firm::where('is_active', true)->value('id');
    });

    // 1. Try to deactivate the only company
    $deactCompRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/companies/{$companyId}/toggle-status");

    $deactCompRes->assertSessionHas('error', 'Cannot deactivate the sole active company.');

    // 2. Try to deactivate the only firm
    $deactFirmRes = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/firms/{$firmId}/toggle-status");

    $deactFirmRes->assertSessionHas('error', 'Cannot deactivate the sole active firm.');

    // Company and Firm must still be active
    $this->tenant->run(function () use ($companyId, $firmId) {
        expect(Company::find($companyId)->is_active)->toBeTrue();
        expect(Firm::find($firmId)->is_active)->toBeTrue();
    });
});
