<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class SubdomainValidator
{
    public const RESERVED = [
        'www', 'app', 'admin', 'api', 'mail', 'smtp', 'ftp', 'cpanel', 'webmail',
        'superadmin', 'billing', 'staging', 'test', 'dev', 'status', 'docs',
        'support', 'help', 'erpsaas', 'central', 'localhost', 'portal', 'dashboard',
        'secure', 'auth', 'login', 'register', 'root', 'static', 'assets', 'cdn',
    ];

    /**
     * Validate subdomain format and availability.
     *
     * @throws ValidationException
     */
    public static function validate(string $subdomain): string
    {
        $normalized = strtolower(trim($subdomain));

        if (! preg_match('/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/', $normalized)) {
            throw ValidationException::withMessages([
                'subdomain' => 'Subdomain must be 3–30 characters long, containing only lowercase letters, numbers, and hyphens (cannot start or end with a hyphen).',
            ]);
        }

        if (in_array($normalized, self::RESERVED, true)) {
            throw ValidationException::withMessages([
                'subdomain' => "The subdomain '{$normalized}' is reserved by the system.",
            ]);
        }

        if (Tenant::where('id', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'subdomain' => "The subdomain '{$normalized}' is already taken.",
            ]);
        }

        return $normalized;
    }

    /**
     * Check if a subdomain is available without throwing an exception.
     */
    public static function isAvailable(string $subdomain): array
    {
        $normalized = strtolower(trim($subdomain));

        if (! preg_match('/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/', $normalized)) {
            return [
                'available' => false,
                'message' => 'Must be 3-30 alphanumeric characters or hyphens.',
            ];
        }

        if (in_array($normalized, self::RESERVED, true)) {
            return [
                'available' => false,
                'message' => "Subdomain '{$normalized}' is reserved.",
            ];
        }

        if (Tenant::where('id', $normalized)->exists()) {
            return [
                'available' => false,
                'message' => "Subdomain '{$normalized}' is already registered.",
            ];
        }

        return [
            'available' => true,
            'message' => 'Subdomain is available!',
            'subdomain' => $normalized,
        ];
    }
}
