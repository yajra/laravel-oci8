<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DeleteWithJoinTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('delete_join_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('delete_join_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
        });

        DB::table('delete_join_users')->insert([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
            ['name' => 'Charlie'],
            ['name' => 'Diana'],
        ]);

        DB::table('delete_join_contacts')->insert([
            ['user_id' => 1, 'status' => 'inactive'],
            ['user_id' => 2, 'status' => 'active'],
            ['user_id' => 3, 'status' => 'inactive'],
            ['user_id' => 4, 'status' => 'inactive'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('delete_join_contacts');
        Schema::dropIfExists('delete_join_users');

        parent::tearDown();
    }

    #[Test]
    public function it_deletes_rows_matching_a_join(): void
    {
        $deleted = DB::table('delete_join_users')
            ->join('delete_join_contacts', function ($join) {
                $join->on('delete_join_users.id', '=', 'delete_join_contacts.user_id')
                    ->where('delete_join_contacts.status', '=', 'inactive');
            })
            ->where('delete_join_users.name', '=', 'Alice')
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertSame(
            ['Bob', 'Charlie', 'Diana'],
            DB::table('delete_join_users')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_deletes_an_ordered_page_of_rows_matching_a_join(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support limit and offset on joined deletes.');
        }

        $deleted = DB::table('delete_join_users as users')
            ->join('delete_join_contacts as contacts', 'users.id', '=', 'contacts.user_id')
            ->where('contacts.status', '=', 'inactive')
            ->orderBy('users.id')
            ->offset(1)
            ->limit(2)
            ->delete();

        $this->assertSame(2, $deleted);
        $this->assertSame(
            ['Alice', 'Bob'],
            DB::table('delete_join_users')->orderBy('id')->pluck('name')->all()
        );
    }
}
