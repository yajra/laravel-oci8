<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class JoinedUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('joined_update_users', function (Blueprint $table) {
            $table->id();
            $table->string('status');
        });

        Schema::create('joined_update_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
        });

        DB::table('joined_update_users')->insert([
            ['id' => 1, 'status' => 'pending'],
            ['id' => 2, 'status' => 'pending'],
            ['id' => 3, 'status' => 'inactive'],
            ['id' => 4, 'status' => 'pending'],
        ]);

        DB::table('joined_update_profiles')->insert([
            ['user_id' => 1, 'type' => 'admin'],
            ['user_id' => 2, 'type' => 'member'],
            ['user_id' => 3, 'type' => 'admin'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('joined_update_profiles');
        Schema::dropIfExists('joined_update_users');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_rows_matching_a_join(): void
    {
        $updated = DB::table('joined_update_users as u')
            ->join('joined_update_profiles as p', function (JoinClause $join) {
                $join->on('u.id', '=', 'p.user_id')
                    ->where('p.type', '=', 'admin');
            })
            ->where('u.status', '=', 'pending')
            ->update(['status' => 'active']);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('joined_update_users', ['id' => 1, 'status' => 'active']);
        $this->assertDatabaseHas('joined_update_users', ['id' => 2, 'status' => 'pending']);
        $this->assertDatabaseHas('joined_update_users', ['id' => 3, 'status' => 'inactive']);
    }

    #[Test]
    public function it_preserves_left_join_semantics(): void
    {
        $updated = DB::table('joined_update_users as u')
            ->leftJoin('joined_update_profiles as p', 'u.id', '=', 'p.user_id')
            ->whereNull('p.user_id')
            ->update(['status' => 'orphaned']);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('joined_update_users', ['id' => 4, 'status' => 'orphaned']);
        $this->assertDatabaseMissing('joined_update_users', ['id' => 1, 'status' => 'orphaned']);
        $this->assertDatabaseMissing('joined_update_users', ['id' => 2, 'status' => 'orphaned']);
        $this->assertDatabaseMissing('joined_update_users', ['id' => 3, 'status' => 'orphaned']);
    }
}
