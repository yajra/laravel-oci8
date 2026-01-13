<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

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
        if (Schema::hasTable('users')) {
            Schema::drop('users');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('bar')->nullable();
            $table->string('baz')->default('test');
        });

        $columns = Schema::getColumns('users');

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
                && str_contains((string) $column['default'], 'test')
        ));
    }

    #[Test]
    public function it_can_get_columns_with_schema()
    {
        if (Schema::hasTable('system.foo')) {
            Schema::drop('system.foo');
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
            fn ($column) => $column['name'] === 'a_float'
                && $column['comment'] === 'a float.'
                && $column['precision'] === 4
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
        if (Schema::hasTable('bar')) {
            Schema::drop('bar');
        }

        if (Schema::hasTable('orders')) {
            Schema::drop('orders');
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('bar', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('bar_id');
            $table->foreign('bar_id')->references('id')->on('orders')->onDelete('cascade');
        });

        $foreignKeys = Schema::getForeignKeys('bar');

        $this->assertCount(1, $foreignKeys);
        $this->assertTrue(collect($foreignKeys)->contains(
            fn ($key) => $key['foreign_schema'] === Schema::getConnection()->getConfig('username')
                && $key['foreign_table'] === 'orders'
                && in_array('id', $key['foreign_columns'])
                && in_array('bar_id', $key['columns'])
        ));
    }

    #[Test]
    public function it_can_add_column_index()
    {
        if (Schema::hasTable('index_table')) {
            Schema::drop('index_table');
        }

        Schema::create('index_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        $indexes = array_column(Schema::getIndexes('index_table'), 'name');

        $this->assertContains('index_table_name_index', $indexes, 'name');
    }

    #[Test]
    public function it_can_add_generated_as_column()
    {
        if (config('database.connections.oracle.server_version') !== '12c') {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        if (Schema::hasTable('generated_as_table')) {
            Schema::drop('generated_as_table');
        }

        Schema::create('generated_as_table', function (Blueprint $table) {
            $table->integer('id')->generatedAs();
            $table->string('name');
        });

        DB::table('generated_as_table')->insert([
            'name' => 'foo',
        ]);

        DB::table('generated_as_table')->insert([
            'name' => 'bar',
        ]);

        DB::table('generated_as_table')->insert([
            'id' => 5,
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('generated_as_table', ['id' => 1, 'name' => 'foo']);
        $this->assertDatabaseHas('generated_as_table', ['id' => 2, 'name' => 'bar']);
        $this->assertDatabaseHas('generated_as_table', ['id' => 5, 'name' => 'foobar']);
    }

    #[Test]
    public function it_can_add_generated_as_on_null_column()
    {
        if (config('database.connections.oracle.server_version') !== '12c') {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        if (Schema::hasTable('generated_as_on_null_table')) {
            Schema::drop('generated_as_on_null_table');
        }

        Schema::create('generated_as_on_null_table', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->onNull();
            $table->string('name');
        });

        DB::table('generated_as_on_null_table')->insert([
            'name' => 'foo',
        ]);

        DB::table('generated_as_on_null_table')->insert([
            'id' => null,
            'name' => 'bar',
        ]);

        DB::table('generated_as_on_null_table')->insert([
            'id' => 5,
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('generated_as_on_null_table', ['id' => 1, 'name' => 'foo']);
        $this->assertDatabaseHas('generated_as_on_null_table', ['id' => 2, 'name' => 'bar']);
        $this->assertDatabaseHas('generated_as_on_null_table', ['id' => 5, 'name' => 'foobar']);
    }

    #[Test]
    public function it_can_add_generated_as_always_column()
    {
        if (config('database.connections.oracle.server_version') !== '12c') {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        if (Schema::hasTable('generated_as_always_table')) {
            Schema::drop('generated_as_always_table');
        }

        Schema::create('generated_as_always_table', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always();
            $table->string('name');
        });

        DB::table('generated_as_always_table')->insert([
            'name' => 'foo',
        ]);

        DB::table('generated_as_always_table')->insert([
            'name' => 'bar',
        ]);

        DB::table('generated_as_always_table')->insert([
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('generated_as_always_table', ['id' => 1, 'name' => 'foo']);
        $this->assertDatabaseHas('generated_as_always_table', ['id' => 2, 'name' => 'bar']);
        $this->assertDatabaseHas('generated_as_always_table', ['id' => 3, 'name' => 'foobar']);
    }

    #[Test]
    public function it_can_add_generated_as_with_options_column()
    {
        if (config('database.connections.oracle.server_version') !== '12c') {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        if (Schema::hasTable('generated_as_with_options_table')) {
            Schema::drop('generated_as_with_options_table');
        }

        Schema::create('generated_as_with_options_table', function (Blueprint $table) {
            $table->integer('id')->generatedAs('increment by 10 start with 100');
            $table->string('name');
        });

        DB::table('generated_as_with_options_table')->insert([
            'name' => 'foo',
        ]);

        DB::table('generated_as_with_options_table')->insert([
            'name' => 'bar',
        ]);

        DB::table('generated_as_with_options_table')->insert([
            'id' => 3,
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('generated_as_with_options_table', ['id' => 100, 'name' => 'foo']);
        $this->assertDatabaseHas('generated_as_with_options_table', ['id' => 110, 'name' => 'bar']);
        $this->assertDatabaseHas('generated_as_with_options_table', ['id' => 3, 'name' => 'foobar']);
    }

    #[Test]
    public function it_can_add_generated_as_on_null_with_options_column()
    {
        if (config('database.connections.oracle.server_version') !== '12c') {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        if (Schema::hasTable('generated_as_on_null_with_options_table')) {
            Schema::drop('generated_as_on_null_with_options_table');
        }

        Schema::create('generated_as_on_null_with_options_table', function (Blueprint $table) {
            $table->integer('id')->generatedAs('increment by 10 start with 100')->onNull();
            $table->string('name');
        });

        DB::table('generated_as_on_null_with_options_table')->insert([
            'name' => 'foo',
        ]);

        DB::table('generated_as_on_null_with_options_table')->insert([
            'id' => null,
            'name' => 'bar',
        ]);

        DB::table('generated_as_on_null_with_options_table')->insert([
            'id' => 3,
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('generated_as_on_null_with_options_table', ['id' => 100, 'name' => 'foo']);
        $this->assertDatabaseHas('generated_as_on_null_with_options_table', ['id' => 110, 'name' => 'bar']);
        $this->assertDatabaseHas('generated_as_on_null_with_options_table', ['id' => 3, 'name' => 'foobar']);
    }

    #[Test]
    public function it_can_autoincrement_by_using_table_id_function()
    {

        if (Schema::hasTable('autoincrement_test')) {
            Schema::drop('autoincrement_test');
        }

        Schema::create('autoincrement_test', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        DB::table('autoincrement_test')->insert([
            'name' => 'foo',
        ]);

        DB::table('autoincrement_test')->insert([
            'id' => null,
            'name' => 'bar',
        ]);

        DB::table('autoincrement_test')->insert([
            'id' => 4,
            'name' => 'foobar',
        ]);

        $this->assertDatabaseHas('autoincrement_test', ['id' => 1, 'name' => 'foo']);
        $this->assertDatabaseHas('autoincrement_test', ['id' => 2, 'name' => 'bar']);
        $this->assertDatabaseHas('autoincrement_test', ['id' => 4, 'name' => 'foobar']);
    }
}
