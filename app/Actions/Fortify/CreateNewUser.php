<?php

namespace App\Actions\Fortify;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // 1. Normalize and Trim Input (Except Passwords)
        foreach ($input as $key => $value) {
            if (is_string($value) && ! in_array($key, ['password', 'password_confirmation'])) {
                $trimmed = trim($value);
                $input[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'max:10', 'regex:/^[6-9]\d{9}$/'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_name' => ['required', 'string', 'max:150', Rule::unique(Tenant::class, 'name')],
            'industry' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20', Rule::unique(Tenant::class, 'tax_id_number')],
        ], [
            'company_name.required' => 'Company / Business Legal Name is required.',
            'company_name.unique' => 'This business is already registered.',
            'gstin.unique' => 'This GSTIN is already registered to another account.',
        ])->validate();

        try {
            return DB::transaction(function () use ($input) {
                $rawPrefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $input['company_name']));
                $prefix = substr($rawPrefix, 0, 3) ?: 'ERP';

                $tenant = Tenant::create([
                    'name' => trim($input['company_name']),
                    'slug' => Str::slug($input['company_name']).'-'.rand(100, 999),
                    'tagline' => 'GST Billing, Inventory & Operations',
                    'industry' => $input['industry'],
                    'currency_code' => 'INR',
                    'currency_symbol' => '₹',
                    'tax_id_label' => 'GSTIN',
                    'tax_id_number' => ! empty($input['gstin']) ? strtoupper(trim($input['gstin'])) : null,
                    'email' => strtolower(trim($input['email'])),
                    'phone' => trim($input['phone']),
                    'city' => ! empty($input['city']) ? trim($input['city']) : null,
                    'state' => ! empty($input['state']) ? trim($input['state']) : 'Gujarat',
                    'country' => 'India',
                    'invoice_prefix' => $prefix.'-INV',
                    'quotation_prefix' => $prefix.'-QUO',
                    'po_prefix' => $prefix.'-PO',
                    'shipment_prefix' => $prefix.'-SHP',
                    'plan' => 'GROWTH_ENTERPRISE',
                    'is_active' => true,
                ]);

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => trim($input['name']),
                    'email' => strtolower(trim($input['email'])),
                    'phone' => trim($input['phone']),
                    'password' => Hash::make($input['password']),
                    'role' => 'OWNER',
                    'designation' => 'Business Owner / Managing Director',
                    'is_active' => true,
                ]);

                $tenant->users()->attach($user->id, [
                    'role' => 'OWNER',
                    'is_default' => true,
                ]);

                try {
                    if (class_exists(Role::class)) {
                        Role::firstOrCreate(['name' => 'OWNER', 'guard_name' => 'web']);
                        $user->assignRole('OWNER');
                    }
                } catch (\Throwable $e) {
                    Log::warning('Role assignment notice: '.$e->getMessage());
                }

                return $user;
            });
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('User registration failed: '.$e->getMessage(), [
                'exception' => $e,
                'email' => $input['email'] ?? null,
                'company' => $input['company_name'] ?? null,
            ]);
            throw ValidationException::withMessages([
                'email' => 'Unable to complete registration: '.$e->getMessage(),
            ]);
        }
    }
}
