<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProhibitedDatabaseGuardTest extends TestCase
{
    public function test_safety_guard_blocks_saas_erp(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAFETY ERROR: Tests are attempting to use a production ERP database');

        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'saas_erp']);

        // Trigger the check
        $reflection = new \ReflectionMethod($this, 'setUp');
        $reflection->invoke($this);
    }
}
