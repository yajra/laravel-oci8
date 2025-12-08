<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Yajra\Oci8\Query\Processors\OracleProcessor;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_perform_insert()
    {
        $data = ['name' => 'Foo', 'job_id' => null];

        $this->getConnection()->table('jobs')->insert($data);

        $this->assertDatabaseCount('jobs', 1);
    }

    #[Test]
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

    #[Test]
    public function it_can_perform_union_order_query()
    {
        $builder = $this->getBuilder();

        $builder->select('id')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('id')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');

        $this->assertCount(2, $builder->get());
    }

    #[Test]
    public function it_can_insert_and_get_id()
    {
        $lastId = $this->getConnection()->table('users')->max('id');
        $id = $this->getBuilder()->from('users')->insertGetId(['name' => 'foo', 'email' => 'bar']);
        $this->assertSame($lastId + 1, $id);
    }

    #[Test]
    public function it_can_insert_empty_and_get_id()
    {
        if (Schema::hasTable('empty_defaults_table')) {
            Schema::drop('empty_defaults_table');
        }

        Schema::create('empty_defaults_table', function (Blueprint $table) {
            $table->id('id');
            $table->string('name')->default('default');
        });

        $id = $this->getBuilder()->from('empty_defaults_table')->insertGetId([]);

        $this->assertSame(1, $id);
        $this->assertDatabaseHas('empty_defaults_table', [
            'id' => 1,
            'name' => 'default',
        ]);
    }

    protected function getBuilder(): Builder
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();
        $grammar = new OracleGrammar($connection);
        $processor = new OracleProcessor;

        return new Builder($this->getConnection(), $grammar, $processor);
    }
}
