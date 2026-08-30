<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Query\Expression as Raw;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Oci8Connection;
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

        Schema::create('query_builder_lobs', function (Blueprint $table) {
            $table->id('id');
            $table->binary('payload')->nullable();
            $table->string('status');
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
        Schema::drop('query_builder_lobs');

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
    public function it_can_insert_and_get_id()
    {
        $lastId = $this->getConnection()->table('users')->max('id');
        $id = $this->getBuilder()->from('users')->insertGetId(['name' => 'foo', 'email' => 'bar']);
        $this->assertSame($lastId + 1, $id);
    }

    #[Test]
    public function it_returns_query_exception()
    {
        $this->expectException(QueryException::class);

        User::forceCreate([
            'name' => 'test',
            'email' => 'test@test.hu',
            'not_exists' => 'fail',
        ]);
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

    #[Test]
    public function it_can_execute_bound_where_in_clauses_larger_than_oracles_limit(): void
    {
        $matching = $this->getConnection()
            ->table('users')
            ->whereIn('id', range(1, 1001))
            ->count();

        $excluded = $this->getConnection()
            ->table('users')
            ->whereNotIn('id', range(1, 1001))
            ->count();

        $this->assertSame(20, $matching);
        $this->assertSame(0, $excluded);
    }

    #[Test]
    public function it_can_execute_oracles_random_order_expression_with_a_limit(): void
    {
        $users = $this->getConnection()
            ->table('users')
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $this->assertCount(5, $users);
        $this->assertCount(5, $users->pluck('id')->unique());
    }

    #[Test]
    public function it_can_insert_and_update_lobs_through_the_oracle_query_builder(): void
    {
        $id = $this->getConnection()
            ->table('query_builder_lobs')
            ->insertLob(
                ['status' => 'created'],
                ['payload' => 'first payload'],
                'id'
            );

        $updated = $this->getConnection()
            ->table('query_builder_lobs')
            ->where('id', $id)
            ->updateLob(
                ['status' => 'updated'],
                ['payload' => 'updated payload'],
                'id'
            );

        $lob = $this->getConnection()
            ->table('query_builder_lobs')
            ->where('id', $id)
            ->first();

        $this->assertTrue($updated);
        $this->assertSame('updated', $lob->status);
        $this->assertSame('updated payload', $lob->payload);
    }

    #[Test]
    public function it_updates_only_rows_after_an_offset_without_a_limit(): void
    {
        $this->getConnection()->table('jobs')->insert([
            ['name' => 'First', 'job_id' => 1],
            ['name' => 'Second', 'job_id' => 2],
            ['name' => 'Third', 'job_id' => 3],
            ['name' => 'Fourth', 'job_id' => 4],
        ]);

        $updated = $this->getConnection()
            ->table('jobs')
            ->orderBy('id')
            ->offset(2)
            ->update(['name' => 'Updated']);

        $this->assertSame(2, $updated);
        $this->assertSame(
            ['First', 'Second', 'Updated', 'Updated'],
            $this->getConnection()->table('jobs')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_deletes_only_rows_after_an_offset_without_a_limit(): void
    {
        $this->getConnection()->table('jobs')->insert([
            ['name' => 'First', 'job_id' => 1],
            ['name' => 'Second', 'job_id' => 2],
            ['name' => 'Third', 'job_id' => 3],
            ['name' => 'Fourth', 'job_id' => 4],
        ]);

        $deleted = $this->getConnection()
            ->table('jobs')
            ->orderBy('id')
            ->offset(2)
            ->delete();

        $this->assertSame(2, $deleted);
        $this->assertSame(
            ['First', 'Second'],
            $this->getConnection()->table('jobs')->orderBy('id')->pluck('name')->all()
        );
    }

    protected function getBuilder(): Builder
    {
        /** @var Oci8Connection $connection */
        $connection = $this->getConnection();
        $grammar = new OracleGrammar($connection);
        $processor = new OracleProcessor;

        return new Builder($this->getConnection(), $grammar, $processor);
    }
}
