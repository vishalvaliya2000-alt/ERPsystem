<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->beforeEach(function () {
    if (! class_exists(Facade::class)) {
        return;
    }

    $app = Facade::getFacadeApplication();
    if (! $app || ! $app->bound('config')) {
        return;
    }

    $connection = (string) config('database.default');
    $database = (string) config("database.connections.{$connection}.database");
    $prohibitedDatabases = ['saas_erp', 'saas_erp_central', 'saas_erp_production'];

    if ($connection === 'mysql' && in_array(strtolower($database), $prohibitedDatabases, true)) {
        throw new RuntimeException(
            "SAFETY ERROR: Tests are attempting to use a production ERP database [Connection: {$connection}, Database: {$database}]. Tests must only run against saas_erp_test or an isolated test database."
        );
    }
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
