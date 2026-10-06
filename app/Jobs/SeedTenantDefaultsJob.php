<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Department;
use App\Models\Firm;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DepartmentPermissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedTenantDefaultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            // Reset cached roles and permissions
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            // 1. Seed Roles & Permissions
            $permissions = DepartmentPermissionService::allPermissions();

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }

            $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $managerRole = Role::firstOrCreate(['name' => 'department_manager', 'guard_name' => 'web']);
            $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
            $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

            $ownerRole->syncPermissions(Permission::all());
            $adminRole->syncPermissions(Permission::all());

            $managerRole->syncPermissions([]);
            $staffRole->syncPermissions([]);
            $viewerRole->syncPermissions([]);

            // 2. Seed 4 Fixed Departments
            $departments = [
                ['name' => 'Sales', 'code' => 'SALES', 'description' => 'CRM, Quotations, Sales Orders & Customer Billing'],
                ['name' => 'Accounting', 'code' => 'ACC', 'description' => 'Double-Entry Ledgers, Expenses, Vouchers & Financial Reports'],
                ['name' => 'Quality Control', 'code' => 'QC', 'description' => 'Batches, Sample Evaluations, Lab Inspections & Certifications'],
                ['name' => 'Shipping/Dispatch', 'code' => 'DISPATCH', 'description' => 'Commercial Logistics, Lorry Receipts (LR), Transporters & E-way Bills'],
            ];

            foreach ($departments as $dept) {
                Department::firstOrCreate(['code' => $dept['code']], $dept);
            }

            // 3. Seed Default Company & Firm
            $companyName = $this->tenant->name ?? 'Enterprise Company';
            $company = Company::firstOrCreate([
                'legal_name' => $companyName,
            ], [
                'trade_name' => $companyName,
                'is_default' => true,
                'is_active' => true,
            ]);

            Firm::firstOrCreate([
                'company_id' => $company->id,
                'firm_name' => $companyName.' (Main Unit)',
            ], [
                'gstin' => $this->tenant->gstin,
                'state' => 'Gujarat',
                'state_code' => '24',
                'invoice_prefix' => 'INV',
                'quotation_prefix' => 'QUO',
                'po_prefix' => 'PO',
                'shipment_prefix' => 'SHP',
                'is_default' => true,
                'is_active' => true,
            ]);

            // 4. Seed Standard Indian Chart of Accounts
            $coaAccounts = [
                ['code' => '1010', 'name' => 'Cash Account', 'group' => 'ASSETS', 'sub' => 'CASH'],
                ['code' => '1020', 'name' => 'Bank Account (Current)', 'group' => 'ASSETS', 'sub' => 'BANK'],
                ['code' => '1030', 'name' => 'Sundry Debtors (Receivables)', 'group' => 'ASSETS', 'sub' => 'SUNDRY_DEBTORS'],
                ['code' => '1041', 'name' => 'Input CGST Ledger', 'group' => 'ASSETS', 'sub' => 'TAX_INPUT'],
                ['code' => '1042', 'name' => 'Input SGST Ledger', 'group' => 'ASSETS', 'sub' => 'TAX_INPUT'],
                ['code' => '1043', 'name' => 'Input IGST Ledger', 'group' => 'ASSETS', 'sub' => 'TAX_INPUT'],
                ['code' => '1050', 'name' => 'Fixed Assets (Plant & Machinery)', 'group' => 'ASSETS', 'sub' => 'FIXED_ASSETS'],
                ['code' => '2010', 'name' => 'Sundry Creditors (Payables)', 'group' => 'LIABILITIES', 'sub' => 'SUNDRY_CREDITORS'],
                ['code' => '2021', 'name' => 'Output CGST Ledger', 'group' => 'LIABILITIES', 'sub' => 'TAX_OUTPUT'],
                ['code' => '2022', 'name' => 'Output SGST Ledger', 'group' => 'LIABILITIES', 'sub' => 'TAX_OUTPUT'],
                ['code' => '2023', 'name' => 'Output IGST Ledger', 'group' => 'LIABILITIES', 'sub' => 'TAX_OUTPUT'],
                ['code' => '2030', 'name' => 'TCS Payable (206C 1H)', 'group' => 'LIABILITIES', 'sub' => 'TAX_OUTPUT'],
                ['code' => '3010', 'name' => 'Owner Capital', 'group' => 'EQUITY', 'sub' => 'CAPITAL'],
                ['code' => '4010', 'name' => 'Sales Revenue', 'group' => 'INCOME', 'sub' => 'SALES'],
                ['code' => '5010', 'name' => 'Purchase Account', 'group' => 'EXPENSES', 'sub' => 'DIRECT_EXPENSES'],
                ['code' => '5020', 'name' => 'Freight Inward / Cartage', 'group' => 'EXPENSES', 'sub' => 'DIRECT_EXPENSES'],
                ['code' => '5030', 'name' => 'Depreciation Expense', 'group' => 'EXPENSES', 'sub' => 'INDIRECT_EXPENSES'],
                ['code' => '5040', 'name' => 'General & Administrative Expenses', 'group' => 'EXPENSES', 'sub' => 'INDIRECT_EXPENSES'],
            ];

            foreach ($coaAccounts as $acc) {
                DB::table('chart_of_accounts')->insertOrIgnore([
                    'account_code' => $acc['code'],
                    'account_name' => $acc['name'],
                    'account_group' => $acc['group'],
                    'sub_group' => $acc['sub'],
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Seed Owner User
            $ownerEmail = $this->tenant->owner_email;
            if ($ownerEmail) {
                $password = $this->tenant->owner_password_hash
                    ?? ($this->tenant->data['owner_password_hash'] ?? null)
                    ?? ($this->tenant->data['data']['owner_password_hash'] ?? null)
                    ?? Hash::make($this->tenant->data['owner_raw_password'] ?? 'Password123!');

                $salesDept = Department::where('code', 'SALES')->first();

                $owner = User::firstOrCreate([
                    'email' => $ownerEmail,
                ], [
                    'name' => $this->tenant->owner_name ?? 'Owner',
                    'phone' => $this->tenant->owner_phone,
                    'password' => $password,
                    'department_id' => $salesDept?->id,
                    'role' => 'owner',
                    'is_active' => true,
                ]);

                $owner->syncRoles(['owner']);
                DepartmentPermissionService::syncUserPermissions($owner);
            }
        });
    }
}
