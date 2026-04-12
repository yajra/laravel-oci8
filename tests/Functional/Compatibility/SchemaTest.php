<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class SchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'oracle') {
            DB::statement('begin execute immediate \'drop view "COMPATIBILITY_VIEW"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop type "COMPATIBILITY_TYPE" force\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_TEMP_TABLE"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_DEFERRABLE_POSTS"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_DEFERRABLE_USERS"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_DEFERRABLE_MAIL"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_NOT_VALID_POSTS"\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop table "COMPATIBILITY_NOT_VALID_USERS"\'; exception when others then null; end;');
        } elseif ($driver === 'pgsql') {
            DB::statement('drop view if exists "compatibility_view"');
            DB::statement('drop type if exists "compatibility_type"');
            DB::statement('drop table if exists "compatibility_temp_table"');
            DB::statement('drop table if exists "compatibility_deferrable_posts"');
            DB::statement('drop table if exists "compatibility_deferrable_users"');
            DB::statement('drop table if exists "compatibility_deferrable_mail"');
            DB::statement('drop table if exists "compatibility_not_valid_posts"');
            DB::statement('drop table if exists "compatibility_not_valid_users"');
        }

        if (Schema::hasTable('rename_index_table')) {
            Schema::drop('rename_index_table');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_rename_index()
    {
        Schema::create('rename_index_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        Schema::table('rename_index_table', function (Blueprint $table) {
            $table->renameIndex('rename_index_table_name_index', 'rename_index_table_name_idx');
        });

        $indexes = array_column(Schema::getIndexes('rename_index_table'), 'name');

        $this->assertContains('rename_index_table_name_idx', $indexes);
        $this->assertNotContains('rename_index_table_name_index', $indexes);
    }

    #[Test]
    public function it_can_get_views_from_schema_builder()
    {
        Schema::create('rename_index_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('create view "COMPATIBILITY_VIEW" as select "ID", "NAME" from "RENAME_INDEX_TABLE"');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('create view "compatibility_view" as select "id", "name" from "rename_index_table"');
        } else {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        $view = collect(Schema::getViews())->firstWhere('name', 'compatibility_view');

        $this->assertNotNull($view);
        $this->assertArrayHasKey('schema', $view);
        $this->assertArrayHasKey('schema_qualified_name', $view);
        $this->assertArrayHasKey('definition', $view);
        $this->assertStringContainsString('compatibility_view', $view['schema_qualified_name']);
    }

    #[Test]
    public function it_can_get_types_from_schema_builder()
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('create type "COMPATIBILITY_TYPE" as object ("ID" number(10,0))');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("create type compatibility_type as enum ('draft', 'published')");
        } else {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        $type = collect(Schema::getTypes())->firstWhere('name', 'compatibility_type');

        $this->assertNotNull($type);
        $this->assertArrayHasKey('schema', $type);
        $this->assertArrayHasKey('type', $type);
        $this->assertArrayHasKey('category', $type);
        $this->assertArrayHasKey('implicit', $type);
        $this->assertFalse((bool) $type['implicit']);
    }

    #[Test]
    public function it_can_create_temporary_tables_from_schema_builder()
    {
        if (! in_array(DB::connection()->getDriverName(), ['oracle', 'pgsql'], true)) {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        Schema::create('compatibility_temp_table', function (Blueprint $table) {
            $table->temporary();
            $table->integer('id');
            $table->string('name');
        });

        DB::table('compatibility_temp_table')->insert([
            'id' => 1,
            'name' => 'temporary',
        ]);

        $this->assertSame('temporary', DB::table('compatibility_temp_table')->value('name'));
    }

    #[Test]
    public function it_can_use_deferrable_constraints_from_schema_builder()
    {
        if (! in_array(DB::connection()->getDriverName(), ['oracle', 'pgsql'], true)) {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        Schema::create('compatibility_deferrable_users', function (Blueprint $table) {
            $table->integer('id')->primary();
        });

        Schema::create('compatibility_deferrable_posts', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('compatibility_deferrable_users')
                ->deferrable()
                ->initiallyImmediate(false);
        });

        Schema::create('compatibility_deferrable_mail', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('email');
            $table->unique('email')
                ->deferrable()
                ->initiallyImmediate(false);
        });

        DB::transaction(function () {
            DB::table('compatibility_deferrable_posts')->insert([
                'id' => 10,
                'user_id' => 1,
            ]);

            DB::table('compatibility_deferrable_users')->insert([
                'id' => 1,
            ]);
        });

        $this->assertSame(1, DB::table('compatibility_deferrable_posts')->where('user_id', 1)->count());

        DB::table('compatibility_deferrable_mail')->insert([
            ['id' => 1, 'email' => 'alpha@example.test'],
            ['id' => 2, 'email' => 'beta@example.test'],
        ]);

        DB::transaction(function () {
            DB::table('compatibility_deferrable_mail')
                ->where('id', 1)
                ->update(['email' => 'beta@example.test']);

            DB::table('compatibility_deferrable_mail')
                ->where('id', 2)
                ->update(['email' => 'alpha@example.test']);
        });

        $emails = DB::table('compatibility_deferrable_mail')
            ->orderBy('id')
            ->pluck('email')
            ->all();

        $this->assertSame([
            'beta@example.test',
            'alpha@example.test',
        ], $emails);
    }

    #[Test]
    public function it_can_use_not_valid_foreign_keys_from_schema_builder()
    {
        if (! in_array(DB::connection()->getDriverName(), ['oracle', 'pgsql'], true)) {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        Schema::create('compatibility_not_valid_users', function (Blueprint $table) {
            $table->integer('id')->primary();
        });

        Schema::create('compatibility_not_valid_posts', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
        });

        DB::table('compatibility_not_valid_posts')->insert([
            'id' => 10,
            'user_id' => 999,
        ]);

        Schema::table('compatibility_not_valid_posts', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('compatibility_not_valid_users')
                ->notValid();
        });

        DB::table('compatibility_not_valid_users')->insert([
            'id' => 1,
        ]);

        DB::table('compatibility_not_valid_posts')->insert([
            'id' => 11,
            'user_id' => 1,
        ]);

        $this->assertSame(2, DB::table('compatibility_not_valid_posts')->count());

        try {
            DB::table('compatibility_not_valid_posts')->insert([
                'id' => 12,
                'user_id' => 5000,
            ]);

            $this->fail('Expected the not valid foreign key to reject new invalid rows.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }
    }
}
