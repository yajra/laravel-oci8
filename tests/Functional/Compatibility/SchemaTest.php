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
            DB::statement('begin execute immediate \'drop type "COMPATIBILITY_TYPE_LIST" force\'; exception when others then null; end;');
            DB::statement('begin execute immediate \'drop type "COMPATIBILITY_TYPE" force\'; exception when others then null; end;');
        } elseif ($driver === 'pgsql') {
            DB::statement('drop view if exists "compatibility_view"');
            DB::statement('drop type if exists compatibility_type_list');
            DB::statement('drop type if exists "compatibility_type"');
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
    public function it_can_get_indexes_for_schema_qualified_table_names()
    {
        Schema::create('rename_index_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        $schema = $this->currentSchemaName();
        $indexes = array_column(Schema::getIndexes($schema.'.rename_index_table'), 'name');

        $this->assertContains('rename_index_table_name_index', $indexes);
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
    public function it_returns_collection_types_once_from_schema_builder()
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('create type "COMPATIBILITY_TYPE" as object ("ID" number(10,0))');
            DB::statement('create type "COMPATIBILITY_TYPE_LIST" as table of "COMPATIBILITY_TYPE"');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("create type compatibility_type_list as enum ('draft', 'published')");
        } else {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        $types = collect(Schema::getTypes())->where('name', 'compatibility_type_list');

        $this->assertCount(1, $types);
        $this->assertFalse((bool) $types->first()['implicit']);

        if (DB::connection()->getDriverName() === 'oracle') {
            $this->assertSame('table', $types->first()['type']);
            $this->assertSame('collection', $types->first()['category']);
        }
    }

    private function currentSchemaName(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::selectOne('select current_schema() as schema')->schema;
        }

        return strtolower((string) DB::connection()->getConfig('username'));
    }
}
