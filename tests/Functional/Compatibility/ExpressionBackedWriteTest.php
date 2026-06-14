<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ExpressionBackedWriteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('expression_write_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        DB::table('expression_write_users')->insert([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('expression_write_users');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_a_limited_from_sub_target(): void
    {
        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->markTestSkipped('This test covers Oracle inline-view writes.');
        }

        $updated = DB::query()
            ->fromSub(function ($query) {
                $query->select(['id', 'name'])
                    ->from('expression_write_users')
                    ->where('name', '=', 'Alice');
            }, 'users')
            ->where('users.name', '=', 'Alice')
            ->orderBy('users.id')
            ->limit(1)
            ->update(['name' => 'Updated Alice']);

        $this->assertSame(1, $updated);
        $this->assertSame(
            ['Updated Alice', 'Bob'],
            DB::table('expression_write_users')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_deletes_a_limited_from_raw_target(): void
    {
        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->markTestSkipped('This test covers Oracle inline-view writes.');
        }

        $deleted = DB::query()
            ->fromRaw(
                '(select "ID", "NAME" from "EXPRESSION_WRITE_USERS" where "NAME" = ?) "USERS"',
                ['Alice']
            )
            ->where('users.name', '=', 'Alice')
            ->orderBy('users.id')
            ->limit(1)
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertSame(
            ['Bob'],
            DB::table('expression_write_users')->orderBy('id')->pluck('name')->all()
        );
    }
}
