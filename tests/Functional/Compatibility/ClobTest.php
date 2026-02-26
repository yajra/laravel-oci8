<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ClobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('clob_test', function (Blueprint $table) {
            $table->id();
            $table->mediumText('value');
        });
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('cache')) {
            Schema::drop('cache');
        }

        if (Schema::hasTable('clob_test')) {
            Schema::drop('clob_test');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_insert_clob_upsert()
    {
        if (Schema::hasTable('cache')) {
            Schema::drop('cache');
        }

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        $data = ['key' => 'Foo', 'value' => str_repeat('abcdefghij', 4000), 'expiration' => 0];

        DB::table('cache')->upsert($data, 'key');

        $this->assertDatabaseCount('cache', 1);
        $this->assertEquals($data['value'], DB::table('cache')->first()->value);
    }

    #[Test]
    public function it_can_insert_clob_insert_and_update()
    {
        $data = ['value' => str_repeat('abcdefghij', 4000)];

        DB::table('clob_test')->insert($data);

        $this->assertDatabaseCount('clob_test', 1);
        $this->assertEquals($data['value'], DB::table('clob_test')->where('id', 1)->first()->value);

        $data = ['id' => 1, 'value' => str_repeat('12345abcdefghij', 4000)];

        DB::table('clob_test')->update($data);

        $this->assertDatabaseCount('clob_test', 1);
        $this->assertEquals($data['value'], DB::table('clob_test')->where('id', 1)->first()->value);
    }
}
