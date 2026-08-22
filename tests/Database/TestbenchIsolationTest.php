<?php

namespace Yajra\Oci8\Tests\Database;

use Orchestra\Testbench\TestCase;

class TestbenchIsolationTest extends TestCase
{
    public function test_artisan_can_boot_in_a_fresh_application(): void
    {
        $this->artisan('list', ['--raw' => true])->assertSuccessful();
    }

    public function test_another_application_can_boot_without_leaked_handlers(): void
    {
        $this->artisan('list', ['--raw' => true])->assertSuccessful();
    }
}
