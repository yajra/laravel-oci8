<?php

namespace Yajra\Oci8\Tests\Functional;

use Yajra\Oci8\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_perform_bulk_inserts()
    {
        $data = [
            ['name' => 'Foo', 'job_id' => null],
            ['name' => 'Bar', 'job_id' => 1],
            ['name' => 'Test', 'job_id' => 2],
            ['name' => null, 'job_id' => 4],
            ['name' => null, 'job_id' => null],
        ];

        $this->getConnection()->table('jobs')->insert($data);

        $this->assertDatabaseCount('jobs', 5);
    }
}
