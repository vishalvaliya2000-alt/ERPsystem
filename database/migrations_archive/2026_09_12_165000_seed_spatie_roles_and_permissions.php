<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Core Permissions
        $permissions = [
            'view-dashboard',
            'manage-orders',
            'manage-customers',
            'manage-shipments',
            'manage-invoices',
            'manage-team',
            'manage-settings',
            'export-excel',
            'sync-excel',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Create Roles
        $roles = [
            'OWNER' => $permissions,
            'ADMIN' => $permissions,
            'MANAGING_DIRECTOR' => $permissions,
            'SALES' => ['view-dashboard', 'manage-orders', 'manage-customers', 'export-excel'],
            'DISPATCH' => ['view-dashboard', 'manage-shipments', 'manage-orders', 'export-excel'],
            'ACCOUNTS' => ['view-dashboard', 'manage-invoices', 'manage-customers', 'export-excel'],
            'VIEWER' => ['view-dashboard'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePerms);
        }

        // 3. Map existing users to Spatie roles based on their current role column
        $users = User::all();
        foreach ($users as $user) {
            $currentRole = strtoupper($user->role ?? 'ADMIN');
            if (isset($roles[$currentRole])) {
                $user->assignRole($currentRole);
            } else {
                $user->assignRole('ADMIN');
            }
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::whereIn('name', ['OWNER', 'ADMIN', 'MANAGING_DIRECTOR', 'SALES', 'DISPATCH', 'ACCOUNTS', 'VIEWER'])->delete();
        Permission::whereIn('name', [
            'view-dashboard',
            'manage-orders',
            'manage-customers',
            'manage-shipments',
            'manage-invoices',
            'manage-team',
            'manage-settings',
            'export-excel',
            'sync-excel',
        ])->delete();
    }
};
