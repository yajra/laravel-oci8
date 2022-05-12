<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Yajra\Oci8\Tests\TestCase;

class SchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_get_column_type()
    {
        $type = Schema::getColumnType('users', 'name');

        $this->assertEquals('string', $type);
    }
}
