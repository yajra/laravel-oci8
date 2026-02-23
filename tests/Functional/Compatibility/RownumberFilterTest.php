<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;

class RownumberFilterTest extends TestCase
{
    #[Test]
    public function it_removes_rownumber_in_pagination()
    {
        $expected = User::query()->limit(2)->orderBy('id')->get()->toArray();
        $this->assertArrayNotHasKey('rn', $expected[0]);
        $this->assertArrayNotHasKey('rn', $expected[1]);
    }
}
