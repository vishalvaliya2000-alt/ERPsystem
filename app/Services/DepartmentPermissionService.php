<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DepartmentPermissionService
{
    /**
     * Complete registry of all granular module and action permissions.
     *
     * @return array<string>
     */
    public static function allPermissions(): array
    {
        return [
            // CRM / Leads
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.convert', 'crm.export',

            // Quotations
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete', 'quotations.approve', 'quotations.convert', 'quotations.export',

            // Sales Orders
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.approve', 'orders.export',

            // Party Pricing
            'price_lists.view', 'price_lists.create', 'price_lists.edit', 'price_lists.delete',

            // Invoices & Billing
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.approve', 'invoices.export', 'invoices.view_profit',

            // Accounting & Ledgers
            'accounting.view', 'accounting.vouchers.create', 'accounting.vouchers.edit', 'accounting.vouchers.delete', 'accounting.vouchers.approve', 'accounting.reports', 'accounting.export',

            // Payments & Collections
            'payments.view', 'payments.create', 'payments.edit', 'payments.delete', 'payments.export',

            // Expenses & Input Tax Credit
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete', 'expenses.approve', 'expenses.export',

            // Fixed Assets
            'assets.view', 'assets.create', 'assets.edit', 'assets.delete', 'assets.depreciate',

            // Tax Reports (TDS / TCS)
            'tax_reports.view', 'tax_reports.export',

            // Quality Control & Samples
            'qc.view', 'qc.create', 'qc.edit', 'qc.delete', 'qc.approve', 'qc.trials', 'qc.export',
            'samples.view', 'samples.create', 'samples.edit', 'samples.delete', 'samples.evaluate', 'samples.export',

            // Shipping, Logistics & E-Way Bills
            'shipments.view', 'shipments.create', 'shipments.edit', 'shipments.delete', 'shipments.approve', 'shipments.export', 'shipments.eway',
            'transporters.view', 'transporters.manage',

            // User & Department Administration
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage_department',
            'settings.manage',
            'recycle_bin.restore',
        ];
    }

    /**
     * Get default permissions for a specific department and role combination.
     *
     * @return array<string>
     */
    public static function getDepartmentPermissions(string $departmentCode, string $role): array
    {
        $code = strtoupper($departmentCode);
        $role = strtolower($role);

        if ($role === 'owner' || $role === 'admin') {
            return static::allPermissions();
        }

        if ($role === 'viewer') {
            return [
                'crm.view',
                'quotations.view',
                'orders.view',
                'shipments.view',
                'invoices.view',
                'accounting.view',
                'expenses.view',
                'assets.view',
                'samples.view',
                'qc.view',
            ];
        }

        $basePermissions = match ($code) {
            'SALES' => [
                'crm.view', 'crm.create', 'crm.edit', 'crm.convert',
                'quotations.view', 'quotations.create', 'quotations.edit',
                'orders.view', 'orders.create', 'orders.edit',
                'invoices.create', 'invoices.view',
                'price_lists.view',
            ],
            'ACC' => [
                'accounting.view', 'accounting.vouchers.create',
                'payments.view', 'payments.create',
                'expenses.view', 'expenses.create',
                'assets.view',
                'tax_reports.view',
                'invoices.view',
            ],
            'QC' => [
                'qc.view', 'qc.create', 'qc.edit',
                'samples.view', 'samples.create', 'samples.edit', 'samples.evaluate',
            ],
            'DISPATCH' => [
                'shipments.view', 'shipments.create', 'shipments.edit',
                'shipments.eway',
                'transporters.view',
            ],
            default => [
                'crm.view', 'quotations.view', 'orders.view', 'shipments.view', 'invoices.view',
            ],
        };

        if ($role === 'department_manager') {
            $managerExtra = match ($code) {
                'SALES' => [
                    'crm.delete', 'crm.export',
                    'quotations.delete', 'quotations.approve', 'quotations.convert', 'quotations.export',
                    'orders.delete', 'orders.approve', 'orders.export',
                    'price_lists.create', 'price_lists.edit', 'price_lists.delete',
                    'invoices.edit', 'invoices.export', 'invoices.view_profit',
                    'users.manage_department',
                ],
                'ACC' => [
                    'accounting.vouchers.edit', 'accounting.vouchers.delete', 'accounting.vouchers.approve', 'accounting.reports', 'accounting.export',
                    'payments.edit', 'payments.delete', 'payments.export',
                    'expenses.edit', 'expenses.delete', 'expenses.approve', 'expenses.export',
                    'assets.create', 'assets.edit', 'assets.delete', 'assets.depreciate',
                    'tax_reports.export',
                    'users.manage_department',
                ],
                'QC' => [
                    'qc.delete', 'qc.approve', 'qc.trials', 'qc.export',
                    'samples.delete', 'samples.export',
                    'users.manage_department',
                ],
                'DISPATCH' => [
                    'shipments.delete', 'shipments.approve', 'shipments.export',
                    'transporters.manage',
                    'users.manage_department',
                ],
                default => ['users.manage_department'],
            };

            return array_values(array_unique(array_merge($basePermissions, $managerExtra)));
        }

        return $basePermissions;
    }

    /**
     * Synchronize a user's permissions according to their department and role.
     */
    public static function syncUserPermissions(User $user): void
    {
        $roleName = strtolower($user->role ?: 'staff');

        // 1. Assign Spatie role
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->syncRoles([$role]);

        // 2. If owner or admin, grant all
        if (in_array($roleName, ['owner', 'admin'], true)) {
            $user->syncPermissions(Permission::all());

            return;
        }

        // 3. For department manager or staff, grant department-specific permissions
        $deptCode = $user->department?->code;
        if ($deptCode) {
            $permissions = static::getDepartmentPermissions($deptCode, $roleName);
            $user->syncPermissions($permissions);
        } else {
            $user->syncPermissions(static::getDepartmentPermissions('GENERIC', $roleName));
        }
    }

    /**
     * Check if an actor can manage a target user.
     * Owner & Admin can manage anyone.
     * Department Manager can manage staff within their own department, but not other managers/admins/owners.
     */
    public static function canManage(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false; // Cannot delete/deactivate self
        }

        if ($actor->isOwner()) {
            return true;
        }

        if ($actor->isAdmin()) {
            return ! $target->isOwner();
        }

        if ($actor->isDepartmentManager()) {
            // Can only manage staff in their own department
            $isSameDept = $actor->department_id && ($actor->department_id === $target->department_id);
            $isTargetStaff = in_array(strtolower($target->role), ['staff', 'viewer'], true);

            return $isSameDept && $isTargetStaff;
        }

        return false;
    }
}
