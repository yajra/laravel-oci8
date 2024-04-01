<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Tests\TestCase;

class SchemaTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_get_column_type()
    {
        $type = Schema::getColumnType('users', 'name');

        $this->assertEquals('varchar2', $type);
    }

    #[Test]
    public function it_can_set_auto_increment_starting_value()
    {
        if (Schema::hasTable('auto_increment_starting_value')) {
            Schema::drop('auto_increment_starting_value');
        }

        Schema::create('auto_increment_starting_value', function (Blueprint $blueprint) {
            $blueprint->increments('id')->startingValue(1000);
            $blueprint->string('email');
        });

        DB::table('auto_increment_starting_value')->insert([
            'email' => 'test@email.com',
        ]);

        $this->assertDatabaseCount('auto_increment_starting_value', 1);
        $increment = DB::table('auto_increment_starting_value')->first();
        $this->assertEquals(1000, $increment->id);
    }

    #[Test]
    public function it_can_set_auto_increment_start_value()
    {
        if (Schema::hasTable('auto_increment_start')) {
            Schema::drop('auto_increment_start');
        }

        Schema::create('auto_increment_start', function (Blueprint $blueprint) {
            $blueprint->increments('id')->start(1000);
            $blueprint->string('email');
        });

        DB::table('auto_increment_start')->insert([
            'email' => 'test@email.com',
        ]);

        $this->assertDatabaseCount('auto_increment_start', 1);
        $increment = DB::table('auto_increment_start')->first();
        $this->assertEquals(1000, $increment->id);
    }

    #[Test]
    public function it_can_get_columns()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('bar')->nullable();
            $table->string('baz')->default('test');
        });

        $columns = Schema::getColumns('foo');

        $this->assertArrayHasKey('auto_increment', $columns[0]);
        $this->assertCount(3, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'id' && $column['type'] === 'bigint' && $column['nullable'] === false
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'bar' && $column['nullable'] === true
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'baz'
                && $column['nullable'] === false
                && str_contains($column['default'], 'test')
        ));
    }

    #[Test]
    public function it_can_get_columns_with_schema()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        Schema::create('system.foo', function (Blueprint $table) {
            $table->string('bar')->nullable();
        });

        $columns = Schema::getColumns('system.foo');

        $this->assertCount(1, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'bar' && $column['nullable'] === true
        ));
    }

    #[Test]
    public function it_can_get_columns_comments()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        Schema::create('foo', function (Blueprint $table) {
            $table->string('bar')->nullable()->comment('Some comment here.');
        });

        $columns = Schema::getColumns('foo');

        $this->assertCount(1, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'bar' && $column['comment'] === 'Some comment here.'
        ));
    }

    #[Test]
    public function it_can_get_columns_number_precision()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        Schema::create('foo', function (Blueprint $table) {
            $table->float('a_float', 4)->comment('a float.');
        });

        $columns = Schema::getColumns('foo');

        $this->assertCount(1, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'a_float' && $column['comment'] === 'a float.' && $column['precision'] === 4
        ));
    }

    #[Test]
    public function it_can_get_columns_number_types()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        Schema::create('foo', function (Blueprint $table) {
            $table->decimal('a_decimal')->nullable();
            $table->smallInteger('an_smallint')->nullable();
            $table->integer('an_int')->nullable();
            $table->bigInteger('a_bigint')->nullable();
        });

        $columns = Schema::getColumns('foo');

        $this->assertCount(4, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'a_decimal' && $column['type_name'] === 'number' && $column['type'] === 'decimal'
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'an_smallint' && $column['type_name'] === 'number' && $column['type'] === 'smallint'
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'an_int' && $column['type_name'] === 'number' && $column['type'] === 'int'
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'a_bigint' && $column['type_name'] === 'number' && $column['type'] === 'bigint'
        ));
    }

    #[Test]
    public function it_can_get_foreign_keys()
    {
        if (Schema::hasTable('foo')) {
            Schema::drop('foo');
        }

        if (Schema::hasTable('orders')) {
            Schema::drop('orders');
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('foo_id');
            $table->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        });

        $foreignKeys = Schema::getForeignKeys('system.foo');

        $this->assertCount(1, $foreignKeys);
        $this->assertTrue(collect($foreignKeys)->contains(
            fn ($key) => $key['foreign_schema'] === 'system'
                && $key['foreign_table'] === 'orders'
                && in_array('id', $key['foreign_columns'])
                && in_array('foo_id', $key['columns'])
        ));
    }
}
