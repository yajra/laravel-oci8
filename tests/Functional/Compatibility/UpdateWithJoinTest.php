<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class UpdateWithJoinTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('update_join_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('update_join_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
        });

        DB::table('update_join_users')->insert([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
            ['name' => 'Charlie'],
            ['name' => 'Diana'],
        ]);

        DB::table('update_join_contacts')->insert([
            ['user_id' => 1, 'status' => 'inactive'],
            ['user_id' => 2, 'status' => 'active'],
            ['user_id' => 3, 'status' => 'inactive'],
            ['user_id' => 4, 'status' => 'inactive'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('update_join_contacts');
        Schema::dropIfExists('update_join_users');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_rows_matching_a_join(): void
    {
        $updated = DB::table('update_join_users')
            ->join('update_join_contacts', function ($join) {
                $join->on('update_join_users.id', '=', 'update_join_contacts.user_id')
                    ->where('update_join_contacts.status', '=', 'inactive');
            })
            ->where('update_join_users.name', '=', 'Alice')
            ->update(['name' => 'Updated Alice']);

        $this->assertSame(1, $updated);
        $this->assertSame(
            ['Updated Alice', 'Bob', 'Charlie', 'Diana'],
            DB::table('update_join_users')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_updates_an_ordered_page_of_rows_matching_a_join(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support limit and offset on joined updates.');
        }

        $updated = DB::table('update_join_users as users')
            ->join('update_join_contacts as contacts', 'users.id', '=', 'contacts.user_id')
            ->where('contacts.status', '=', 'inactive')
            ->orderBy('users.id')
            ->offset(1)
            ->limit(2)
            ->update(['name' => 'Updated']);

        $this->assertSame(2, $updated);
        $this->assertSame(
            ['Alice', 'Bob', 'Updated', 'Updated'],
            DB::table('update_join_users')->orderBy('id')->pluck('name')->all()
        );
    }
}
