<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
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

    /** @test */
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

    /** @test */
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

    public function testGetColumns()
    {
        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('bar')->nullable();
            $table->string('baz')->default('test');
        });

        $columns = Schema::getColumns('foo');

        $this->assertCount(3, $columns);
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'ID' && $column['type'] === 'NUMBER' && $column['nullable'] === 'N'
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'BAR' && $column['nullable'] === 'Y'
        ));
        $this->assertTrue(collect($columns)->contains(
            fn ($column) => $column['name'] === 'BAZ'
                && $column['nullable'] === 'N'
                && str_contains($column['default'], 'test')
        ));
    }
}
