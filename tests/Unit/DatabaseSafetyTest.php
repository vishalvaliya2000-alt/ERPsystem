<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_test_suite_uses_saas_erp_test_and_never_production_database(): void
    {
        $connection = config('database.default');
        $databaseName = config("database.connections.{$connection}.database");
        $actualDriver = DB::connection()->getDriverName();
        $actualDatabase = DB::connection()->getDatabaseName();

        echo "\n[TEST DATABASE VERIFICATION]\n";
        echo "Config Default Connection: {$connection}\n";
        echo "Config Database Name: {$databaseName}\n";
        echo "Active PDO Driver: {$actualDriver}\n";
        echo "Active Database Name: {$actualDatabase}\n";

        $this->assertEquals('mysql', $connection, 'Default connection must be mysql');
        $this->assertEquals('mysql', $actualDriver, 'Driver must be mysql');
        $this->assertEquals('saas_erp_test', $databaseName, 'Database must be saas_erp_test');
        $this->assertEquals('saas_erp_test', $actualDatabase, 'Active DB must be saas_erp_test');
        $this->assertNotEquals('sqlite', $connection);
        $this->assertNotEquals('saas_erp', $databaseName);
    }
}
