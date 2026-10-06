<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Services\SubdomainValidator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProvisionTenantAction
{
    /**
     * Provision a brand new tenant with dedicated database, migrations, seeders, and domain.
     *
     * @param array{
     *     name: string,
     *     subdomain: string,
     *     owner_name: string,
     *     owner_email: string,
     *     owner_phone?: string|null,
     *     password: string,
     *     gstin?: string|null,
     *     plan?: string
     * } $data
     *
     * @throws ValidationException
     */
    public function execute(array $data): Tenant
    {
        // 1. Validate subdomain
        $subdomain = SubdomainValidator::validate($data['subdomain']);

        // 2. Compute full domain string
        $tenantRootDomain = config('app.tenant_domain', 'erpsaas.com');
        $host = request()->getHost();

        // If in local development (e.g. accessing via localhost or .localhost), use .localhost
        if (str_ends_with($host, 'localhost') || $host === '127.0.0.1') {
            $domain = "{$subdomain}.localhost";
        } else {
            $domain = "{$subdomain}.{$tenantRootDomain}";
        }

        // 3. Create Tenant (dispatches TenantCreated -> CreateDatabase -> MigrateDatabase -> SeedTenantDefaultsJob)
        $tenant = Tenant::create([
            'id' => $subdomain,
            'name' => $data['name'],
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'gstin' => $data['gstin'] ?? null,
            'plan' => $data['plan'] ?? 'Standard',
            'status' => 'active',
            'owner_password_hash' => Hash::make($data['password']),
            'registered_ip' => request()->ip(),
            'primary_domain' => $domain,
        ]);

        // 4. Create Domain binding (both full hostname and subdomain handle)
        $tenant->domains()->create([
            'domain' => $domain,
        ]);
        if ($domain !== $subdomain) {
            $tenant->domains()->create([
                'domain' => $subdomain,
            ]);
        }

        return $tenant;
    }
}
