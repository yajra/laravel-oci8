<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DatabaseWipeTest extends TestCase
{
    protected function tearDown(): void
    {
        if ($this->isPgsql()) {
            DB::statement('drop view if exists wipe_users_view');
            DB::statement('drop type if exists wipe_address_type');
        } elseif ($this->isMariaDb()) {
            DB::statement('drop view if exists wipe_users_view');
        } else {
            $view = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_views where view_name = ?', ['WIPE_USERS_VIEW'])
            );

            $type = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_types where type_name = ?', ['WIPE_ADDRESS_TYPE'])
            );
        }

        if (isset($view) && (int) $view['object_count'] > 0) {
            DB::statement('drop view wipe_users_view');
        }

        if (isset($type) && (int) $type['object_count'] > 0) {
            DB::statement('drop type wipe_address_type force');
        }

        if (Schema::hasTable('wipe_posts')) {
            Schema::drop('wipe_posts');
        }
        if (Schema::hasTable('wipe_users')) {
            Schema::drop('wipe_users');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_wipe_identity_tables_without_dropping_system_generated_sequences(): void
    {
        Schema::create('wipe_users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });

        Schema::create('wipe_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue(Schema::hasTable('wipe_users'));
        $this->assertTrue(Schema::hasTable('wipe_posts'));

        $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('wipe_users'));
        $this->assertFalse(Schema::hasTable('wipe_posts'));
    }

    #[Test]
    public function it_can_wipe_views_when_requested(): void
    {
        Schema::create('wipe_users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });

        DB::statement('create view wipe_users_view as select id, email from wipe_users');

        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->assertTrue(Schema::hasView('wipe_users_view'));
        } else {
            $view = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_views where view_name = ?', ['WIPE_USERS_VIEW'])
            );

            $this->assertSame(1, (int) $view['object_count']);
        }

        $this->artisan('db:wipe', ['--drop-views' => true, '--force' => true])->assertExitCode(0);

        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->assertFalse(Schema::hasView('wipe_users_view'));
        } else {
            $view = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_views where view_name = ?', ['WIPE_USERS_VIEW'])
            );

            $this->assertSame(0, (int) $view['object_count']);
        }

        $this->assertFalse(Schema::hasTable('wipe_users'));
    }

    #[Test]
    public function it_can_wipe_types_when_requested(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support user-defined type wiping.');
        }

        if ($this->isPgsql()) {
            DB::statement("create type wipe_address_type as enum ('street')");
        } else {
            DB::statement('create type wipe_address_type as object (street varchar2(255))');
        }

        if ($this->isPgsql()) {
            $type = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from pg_type t join pg_namespace n on n.oid = t.typnamespace where t.typname = ? and n.nspname = current_schema()', ['wipe_address_type'])
            );
        } else {
            $type = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_types where type_name = ?', ['WIPE_ADDRESS_TYPE'])
            );
        }

        $this->assertSame(1, (int) $type['object_count']);

        $this->artisan('db:wipe', ['--drop-types' => true, '--force' => true])->assertExitCode(0);

        if ($this->isPgsql()) {
            $type = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from pg_type t join pg_namespace n on n.oid = t.typnamespace where t.typname = ? and n.nspname = current_schema()', ['wipe_address_type'])
            );
        } else {
            $type = array_change_key_case(
                (array) DB::selectOne('select count(*) as object_count from user_types where type_name = ?', ['WIPE_ADDRESS_TYPE'])
            );
        }

        $this->assertSame(0, (int) $type['object_count']);
    }
}
