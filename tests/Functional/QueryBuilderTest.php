<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Yajra\Oci8\Query\Processors\OracleProcessor;
use Yajra\Oci8\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_perform_insert()
    {
        $data = ['name' => 'Foo', 'job_id' => null];

        $this->getConnection()->table('jobs')->insert($data);

        $this->assertDatabaseCount('jobs', 1);
    }

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

    /** @test */
    public function it_can_perform_union_order_query()
    {
        $builder = $this->getBuilder();

        $builder->select('id')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('id')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');

        $this->assertCount(2, $builder->get());
    }

    /** @test */
    public function it_can_insert_get_id_with_empty_values()
    {
        $this->expectExceptionCode(928);
        $id = $this->getBuilder()->from('users')->insertGetId([]);
        // @TODO: insert id should be returned.
    }

    /**
     * @return Builder
     */
    protected function getBuilder(): Builder
    {
        $grammar   = new OracleGrammar;
        $processor = new OracleProcessor;
        $builder   = new Builder($this->getConnection(), $grammar, $processor);

        return $builder;
    }
}
