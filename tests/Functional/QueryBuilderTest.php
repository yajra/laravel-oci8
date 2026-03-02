<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Query\Expression as Raw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Yajra\Oci8\Query\Processors\OracleProcessor;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;

class QueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('jobs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('job_id')->nullable();
        });

        Schema::create('empty_defaults_table', function (Blueprint $table) {
            $table->id('id');
            $table->string('name')->default('default');
        });

        Schema::create('multiple_raw_insert_table', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
        });

        Schema::create('single_raw_insert_table', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
        });

        collect(range(1, 20))->each(function ($i) {
            /** @var User $user */
            User::query()->create([
                'name' => 'Record-'.$i,
                'email' => 'Email-'.$i.'@example.com',
            ]);
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('users');
        Schema::drop('jobs');
        Schema::drop('empty_defaults_table');
        Schema::drop('multiple_raw_insert_table');
        Schema::drop('single_raw_insert_table');

        parent::tearDown();
    }

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
        $this->assertDatabaseHas('jobs', ['name' => 'Foo', 'job_id' => null]);
        $this->assertDatabaseHas('jobs', ['name' => 'Bar', 'job_id' => 1]);
        $this->assertDatabaseHas('jobs', ['name' => 'Test', 'job_id' => 2]);
        $this->assertDatabaseHas('jobs', ['name' => null, 'job_id' => 4]);
        $this->assertDatabaseHas('jobs', ['name' => null, 'job_id' => null]);
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
    public function it_can_perform_union_with_limit()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $builder->limit(5);

        $results = $builder->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_can_perform_union_with_offset()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $builder->skip(15);

        $results = $builder->get();

        $this->assertCount(5, $results); // 20 total records - 15 offset = 5 remaining
    }

    #[Test]
    public function it_can_perform_union_with_limit_and_offset()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $builder->skip(5)->take(10);

        $results = $builder->get();

        $this->assertCount(10, $results);
        // Verify we skipped the first 5 records
        $this->assertGreaterThanOrEqual(6, $results->first()->id);
    }

    #[Test]
    public function it_can_perform_union_with_order_by_and_limit()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $builder->orderBy('id', 'asc')->take(3);

        $results = $builder->get();

        $this->assertCount(3, $results);
        $this->assertEquals(1, $results->first()->id);
        $this->assertEquals(2, $results->get(1)->id);
        $this->assertEquals(3, $results->last()->id);
    }

    #[Test]
    public function it_can_perform_union_all_with_limit()
    {
        $builder = $this->getBuilder();

        $builder->select('name')->from('users')->where('id', '<=', 5);
        $builder->unionAll($this->getBuilder()->select('name')->from('users')->where('id', '<=', 5));
        $builder->take(7);

        $results = $builder->get();

        $this->assertCount(7, $results); // Should get 7 records from the 10 total (5 + 5 duplicate)
    }

    #[Test]
    public function it_can_perform_multiple_unions_with_limit()
    {
        $builder = $this->getBuilder();

        $builder->select('id')->from('users')->where('id', '<=', 5);
        $builder->union($this->getBuilder()->select('id')->from('users')->where('id', '>', 5)->where('id', '<=', 10));
        $builder->union($this->getBuilder()->select('id')->from('users')->where('id', '>', 10)->where('id', '<=', 15));
        $builder->limit(8);

        $results = $builder->get();

        $this->assertCount(8, $results);
    }

    #[Test]
    public function it_can_perform_union_with_limit_one()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $builder->limit(1);

        $results = $builder->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_paginate_union_results()
    {
        $builder = $this->getBuilder();

        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));

        // First page
        $page1 = $builder->skip(0)->take(5)->get();
        $this->assertCount(5, $page1);

        // Second page
        $builder = $this->getBuilder();
        $builder->select('id', 'name')->from('users')->where('id', '<=', 10);
        $builder->union($this->getBuilder()->select('id', 'name')->from('users')->where('id', '>', 10));
        $page2 = $builder->skip(5)->take(5)->get();
        $this->assertCount(5, $page2);

        // Ensure pages are different
        $this->assertNotEquals($page1->first()->id, $page2->first()->id);
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
        $id = $this->getBuilder()->from('empty_defaults_table')->insertGetId([]);

        $this->assertSame(1, $id);
        $this->assertDatabaseHas('empty_defaults_table', [
            'id' => 1,
            'name' => 'default',
        ]);
    }

    #[Test]
    public function it_can_insert_single_using_raw_query()
    {
        $this->getBuilder()->from('single_raw_insert_table')->insert([
            ['name' => new Raw("UPPER('Foo')")],
        ]);

        $this->assertDatabaseHas('single_raw_insert_table', [
            'id' => 1,
            'name' => 'FOO',
        ]);
    }

    #[Test]
    public function it_can_insert_multiple_using_raw_query()
    {
        $this->getBuilder()->from('multiple_raw_insert_table')->insert([
            ['name' => new Raw("UPPER('Foo')")],
            ['name' => new Raw("LOWER('Foo')")],
        ]);

        $this->assertDatabaseHas('multiple_raw_insert_table', [
            'id' => 1,
            'name' => 'FOO',
        ]);
        $this->assertDatabaseHas('multiple_raw_insert_table', [
            'id' => 2,
            'name' => 'foo',
        ]);
    }

    #[Test]
    public function it_keeps_rn_in_pagination_when_selected()
    {
        $expected = User::select(['name', DB::raw('ROWNUM rn')])->limit(2)->orderBy('id')->get()->toArray();
        $notexpected = User::select(['name'])->limit(2)->orderBy('id')->get()->toArray();
        $notexpected2 = User::select(['name'])->limit(2)->orderBy('id')->get()->toArray();

        $this->assertArrayHasKey('rn', $expected[0]);
        $this->assertArrayHasKey('rn', $expected[1]);

        $this->assertArrayNotHasKey('rn', $notexpected[0]);
        $this->assertArrayNotHasKey('rn', $notexpected[1]);

        $this->assertArrayNotHasKey('rn', $notexpected2[0]);
        $this->assertArrayNotHasKey('rn', $notexpected2[1]);
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
