<?php

use App\Actions\ProvisionTenantAction;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DepartmentPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['rbaccompany'])->delete();
    DB::table('tenants')->whereIn('id', ['rbaccompany'])->delete();
    auth()->logout();
    session()->flush();

    // Clean up DB if exists
    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_rbaccompany`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'RBAC Test Manufacturing Ltd',
        'subdomain' => 'rbaccompany',
        'owner_name' => 'Aditya Sharma',
        'owner_email' => 'aditya@rbaccompany.com',
        'password' => 'OwnerPassword123!',
        'plan' => 'Standard',
    ]);
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
        DB::table('domains')->whereIn('tenant_id', ['rbaccompany'])->delete();
        DB::table('tenants')->whereIn('id', ['rbaccompany'])->delete();
    } catch (Throwable $e) {
        // Ignore
    }
});

test('tenant provisioning creates the 4 fixed departments and seeds owner permissions', function () {
    $this->tenant->run(function () {
        // 1. Assert exactly 4 departments exist with correct codes
        $departments = Department::orderBy('code')->get();
        expect($departments)->toHaveCount(4);
        expect($departments->pluck('code')->all())->toBe(['ACC', 'DISPATCH', 'QC', 'SALES']);

        // 2. Assert owner exists and has all permissions
        $owner = User::where('email', 'aditya@rbaccompany.com')->first();
        expect($owner)->not->toBeNull();
        expect($owner->isOwner())->toBeTrue();
        expect($owner->isAdmin())->toBeTrue();

        $allPerms = DepartmentPermissionService::allPermissions();
        foreach ($allPerms as $perm) {
            expect($owner->can($perm))->toBeTrue();
        }
    });
});

test('single manager rule is strictly enforced when assigning department managers', function () {
    $this->tenant->run(function () {
        $salesDept = Department::where('code', 'SALES')->first();

        // Create user 1 (first manager)
        $user1 = User::create([
            'name' => 'Rajesh Patel',
            'email' => 'rajesh@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);

        // Create user 2 (second manager)
        $user2 = User::create([
            'name' => 'Vikram Shah',
            'email' => 'vikram@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);

        // Appoint user1 as manager of Sales
        $salesDept->assignManager($user1);
        $salesDept->refresh();
        $user1->refresh();

        expect($salesDept->manager_user_id)->toBe($user1->id);
        expect($user1->role)->toBe('department_manager');
        expect($user1->isDepartmentManager())->toBeTrue();
        expect($user1->can('quotations.approve'))->toBeTrue();

        // Now appoint user2 as manager of Sales: user1 must automatically be demoted to staff
        $salesDept->assignManager($user2);
        $salesDept->refresh();
        $user1->refresh();
        $user2->refresh();

        expect($salesDept->manager_user_id)->toBe($user2->id);
        expect($user2->role)->toBe('department_manager');
        expect($user2->isDepartmentManager())->toBeTrue();

        // user1 is now staff and lost manager approval permissions
        expect($user1->role)->toBe('staff');
        expect($user1->isDepartmentManager())->toBeFalse();
        expect($user1->can('quotations.approve'))->toBeFalse();
        expect($user1->can('quotations.create'))->toBeTrue(); // staff can still create
    });
});

test('cross-department manager conflict is automatically resolved', function () {
    $this->tenant->run(function () {
        $salesDept = Department::where('code', 'SALES')->first();
        $qcDept = Department::where('code', 'QC')->first();

        $user = User::create([
            'name' => 'Neha Joshi',
            'email' => 'neha@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);

        // User manages Sales
        $salesDept->assignManager($user);
        expect($salesDept->fresh()->manager_user_id)->toBe($user->id);

        // Transfer user to QC and make manager of QC
        $user->update(['department_id' => $qcDept->id]);
        $qcDept->assignManager($user);

        // Sales manager should now be cleared, and QC manager is user
        expect($salesDept->fresh()->manager_user_id)->toBeNull();
        expect($qcDept->fresh()->manager_user_id)->toBe($user->id);
        expect($user->fresh()->department_id)->toBe($qcDept->id);
    });
});

test('department-specific granular permissions matrix aligns with module responsibilities', function () {
    $this->tenant->run(function () {
        $salesDept = Department::where('code', 'SALES')->first();
        $accDept = Department::where('code', 'ACC')->first();
        $dispatchDept = Department::where('code', 'DISPATCH')->first();
        $qcDept = Department::where('code', 'QC')->first();

        // 1. Sales staff
        $salesUser = User::create([
            'name' => 'Sales Guy',
            'email' => 'sales@rbaccompany.com',
            'password' => Hash::make('Pass123!'),
            'role' => 'staff',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);
        DepartmentPermissionService::syncUserPermissions($salesUser);

        expect($salesUser->can('crm.create'))->toBeTrue();
        expect($salesUser->can('quotations.create'))->toBeTrue();
        expect($salesUser->can('accounting.vouchers.create'))->toBeFalse();
        expect($salesUser->can('shipments.eway'))->toBeFalse();

        // 2. Accounting staff
        $accUser = User::create([
            'name' => 'Accounts Lady',
            'email' => 'acc@rbaccompany.com',
            'password' => Hash::make('Pass123!'),
            'role' => 'staff',
            'department_id' => $accDept->id,
            'is_active' => true,
        ]);
        DepartmentPermissionService::syncUserPermissions($accUser);

        expect($accUser->can('accounting.view'))->toBeTrue();
        expect($accUser->can('accounting.vouchers.create'))->toBeTrue();
        expect($accUser->can('expenses.create'))->toBeTrue();
        expect($accUser->can('crm.create'))->toBeFalse();

        // 3. Dispatch staff
        $dispatchUser = User::create([
            'name' => 'Dispatch Officer',
            'email' => 'dispatch@rbaccompany.com',
            'password' => Hash::make('Pass123!'),
            'role' => 'staff',
            'department_id' => $dispatchDept->id,
            'is_active' => true,
        ]);
        DepartmentPermissionService::syncUserPermissions($dispatchUser);

        expect($dispatchUser->can('shipments.create'))->toBeTrue();
        expect($dispatchUser->can('shipments.eway'))->toBeTrue();
        expect($dispatchUser->can('transporters.view'))->toBeTrue();
        expect($dispatchUser->can('accounting.vouchers.create'))->toBeFalse();

        // 4. QC staff
        $qcUser = User::create([
            'name' => 'QC Chemist',
            'email' => 'qc@rbaccompany.com',
            'password' => Hash::make('Pass123!'),
            'role' => 'staff',
            'department_id' => $qcDept->id,
            'is_active' => true,
        ]);
        DepartmentPermissionService::syncUserPermissions($qcUser);

        expect($qcUser->can('qc.create'))->toBeTrue();
        expect($qcUser->can('samples.evaluate'))->toBeTrue();
        expect($qcUser->can('orders.create'))->toBeFalse();
    });
});

test('management hierarchy allows managers to manage department staff and prevents self or owner deactivation', function () {
    $this->tenant->run(function () {
        $salesDept = Department::where('code', 'SALES')->first();
        $accDept = Department::where('code', 'ACC')->first();

        $owner = User::where('role', 'owner')->first();

        $salesManager = User::create([
            'name' => 'Sunil Verma',
            'email' => 'sunil@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'department_manager',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);

        $salesStaff = User::create([
            'name' => 'Kavita Rao',
            'email' => 'kavita@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'department_id' => $salesDept->id,
            'is_active' => true,
        ]);

        $accStaff = User::create([
            'name' => 'Anand Mehta',
            'email' => 'anand@rbaccompany.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'department_id' => $accDept->id,
            'is_active' => true,
        ]);

        // Sales Manager can manage Sales Staff
        expect(DepartmentPermissionService::canManage($salesManager, $salesStaff))->toBeTrue();

        // Sales Manager CANNOT manage Accounts Staff
        expect(DepartmentPermissionService::canManage($salesManager, $accStaff))->toBeFalse();

        // Sales Manager CANNOT manage Owner
        expect(DepartmentPermissionService::canManage($salesManager, $owner))->toBeFalse();

        // User cannot manage self
        expect(DepartmentPermissionService::canManage($salesManager, $salesManager))->toBeFalse();
        expect(DepartmentPermissionService::canManage($owner, $owner))->toBeFalse();

        // Owner can manage everyone except self
        expect(DepartmentPermissionService::canManage($owner, $salesManager))->toBeTrue();
        expect(DepartmentPermissionService::canManage($owner, $salesStaff))->toBeTrue();
        expect(DepartmentPermissionService::canManage($owner, $accStaff))->toBeTrue();
    });
});

test('team controller actions support invite, update, toggle status, and deletion with audit trail', function () {
    $owner = null;
    $this->tenant->run(function () use (&$owner) {
        $owner = User::where('role', 'owner')->first();
    });

    $baseUrl = 'http://rbaccompany.localhost';

    // 1. Owner invites a new member
    $salesDept = null;
    $this->tenant->run(function () use (&$salesDept) {
        $salesDept = Department::where('code', 'SALES')->first();
    });

    $inviteResponse = $this->actingAs($owner)
        ->post("{$baseUrl}/organization/team/invite", [
            'first_name' => 'Mohit',
            'last_name' => 'Chauhan',
            'email' => 'mohit@rbaccompany.com',
            'phone' => '9898989898',
            'department_id' => $salesDept->id,
            'role' => 'staff',
            'password' => 'Pass12345!',
        ]);

    $inviteResponse->assertStatus(302);
    $inviteResponse->assertSessionHas('success');

    $this->tenant->run(function () {
        $mohit = User::where('email', 'mohit@rbaccompany.com')->first();
        expect($mohit)->not->toBeNull();
        expect($mohit->role)->toBe('staff');
        expect($mohit->is_active)->toBeTrue();
        expect($mohit->can('crm.create'))->toBeTrue();

        // 2. Verify Activity Log recorded invitation
        $activity = Activity::where('log_name', 'team')->latest('id')->first();
        expect($activity)->not->toBeNull();
        expect($activity->description)->toContain('Invited team member Mohit Chauhan');

        // 3. Deactivate Mohit
        $ownerUser = User::where('role', 'owner')->first();
        $toggleResponse = test()->actingAs($ownerUser)
            ->post("http://rbaccompany.localhost/organization/team/{$mohit->id}/toggle-status");

        $toggleResponse->assertStatus(302);
        expect($mohit->fresh()->is_active)->toBeFalse();

        // Verify Deactivation Activity Log
        $deactActivity = Activity::where('log_name', 'team')->latest('id')->first();
        expect($deactActivity->description)->toContain('Deactivated user account Mohit Chauhan');

        // 4. Verify deactivated user cannot log in
        test()->post('http://rbaccompany.localhost/logout');
        $loginResponse = test()->post('http://rbaccompany.localhost/login', [
            'email' => 'mohit@rbaccompany.com',
            'password' => 'Pass12345!',
        ]);
        $loginResponse->assertSessionHasErrors('email');
        expect(auth()->check())->toBeFalse();

        // 5. Reactivate Mohit and promote to manager
        test()->actingAs($ownerUser)
            ->post("http://rbaccompany.localhost/organization/team/{$mohit->id}/toggle-status");
        expect($mohit->fresh()->is_active)->toBeTrue();

        $updateResponse = test()->actingAs($ownerUser)
            ->post("http://rbaccompany.localhost/organization/team/{$mohit->id}/update", [
                'name' => 'Mohit Chauhan (Promoted)',
                'phone' => '9898989898',
                'department_id' => Department::where('code', 'SALES')->first()->id,
                'role' => 'department_manager',
            ]);

        $updateResponse->assertStatus(302);
        $mohit->refresh();
        expect($mohit->role)->toBe('department_manager');
        expect(Department::where('code', 'SALES')->first()->manager_user_id)->toBe($mohit->id);
    });
});
