<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ThreadCountTest extends TestCase
{
    #[Test]
    public function it_can_return_the_open_connection_count()
    {
        $threadCount = $this->getConnection()->threadCount();

        if ($threadCount === null) {
            $this->markTestSkipped('Open connection count is not available in this Oracle environment.');
        }

        $this->assertIsNumeric($threadCount);
        $this->assertGreaterThan(0, (int) $threadCount);
    }
}
