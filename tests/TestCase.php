<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Enforce safe test database connection for testing
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'saas_erp_test');

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        $prohibitedDatabases = [
            'saas_erp',
            'saas_erp_central',
            'saas_erp_production',
        ];

        if ($connection === 'mysql' && in_array(strtolower($database), $prohibitedDatabases, true)) {
            throw new RuntimeException(
                "SAFETY ERROR: Tests are attempting to use a production ERP database [Connection: {$connection}, Database: {$database}]. Tests must only run against saas_erp_test or an isolated test database."
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            PreventRequestForgery::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $prohibitedDatabases = [
            'saas_erp',
            'saas_erp_central',
            'saas_erp_production',
        ];

        if ($connection === 'mysql' && in_array(strtolower($database), $prohibitedDatabases, true)) {
            throw new RuntimeException(
                "SAFETY ERROR: Tests are attempting to use a production ERP database [Connection: {$connection}, Database: {$database}]. Tests must only run against saas_erp_test or an isolated test database."
            );
        }
    }
}
