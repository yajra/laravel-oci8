<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ReorderJoinsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('orders');
        Schema::dropIfExists('stats');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->integer('amount');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
        });

        // Minimal seed data (not strictly required for toSql(),
        // but ensures tables are valid and usable if query is executed)
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Alice'],
        ]);

        DB::table('stats')->insert([
            ['user_id' => 1, 'amount' => 100],
        ]);

        DB::table('orders')->insert([
            ['user_id' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::drop('users');
        Schema::drop('stats');
        Schema::drop('orders');

        parent::tearDown();
    }

    #[Test]
    public function it_can_join_order_is_not_broken_when_later_join_references_a_derived_table(): void
    {
        $query = DB::table('users')
            ->joinSub(
                DB::table('stats')
                    ->select('user_id', DB::raw('SUM(amount) as total'))
                    ->groupBy('user_id'),
                'derived_stats',
                'users.id',
                '=',
                'derived_stats.user_id'
            )
            ->join('orders', 'orders.user_id', '=', 'derived_stats.user_id');

        $query->get();
    }

    #[Test]
    public function it_can_join_order_is_reordered_when_a_join_references_a_later_derived_table(): void
    {
        $query = DB::table('users')
            ->join('orders', 'orders.user_id', '=', 'derived_stats.user_id')
            ->joinSub(
                DB::table('stats')
                    ->select('user_id', DB::raw('SUM(amount) as total'))
                    ->groupBy('user_id'),
                'derived_stats',
                'users.id',
                '=',
                'derived_stats.user_id'
            );

        $query->get();
    }

    #[Test]
    public function it_reorders_when_a_plain_table_alias_is_referenced_before_it_is_joined(): void
    {
        $sql = DB::table('users')
            ->join('orders as o', 'o.user_id', '=', 's.user_id')
            ->join('stats as s', 'users.id', '=', 's.user_id')
            ->toSql();

        $normalized = strtoupper($sql);

        $this->assertLessThan(
            strpos($normalized, 'INNER JOIN "ORDERS" "O"'),
            strpos($normalized, 'INNER JOIN "STATS" "S"')
        );
    }

    #[Test]
    public function it_keeps_independent_joins_stable_when_reordering_is_needed(): void
    {
        $sql = DB::table('users')
            ->join('orders as o', 'o.user_id', '=', 's.user_id')
            ->join('orders as o2', 'o2.user_id', '=', 'users.id')
            ->join('stats as s', 'users.id', '=', 's.user_id')
            ->toSql();

        $normalized = strtoupper($sql);

        $o2Pos = strpos($normalized, 'INNER JOIN "ORDERS" "O2"');
        $sPos = strpos($normalized, 'INNER JOIN "STATS" "S"');
        $oPos = strpos($normalized, 'INNER JOIN "ORDERS" "O"');

        $this->assertNotFalse($o2Pos);
        $this->assertNotFalse($sPos);
        $this->assertNotFalse($oPos);
        $this->assertLessThan($sPos, $o2Pos);
        $this->assertLessThan($oPos, $sPos);
    }

    #[Test]
    public function it_ignores_non_string_join_where_operands_when_collecting_dependencies(): void
    {
        $query = DB::table('users')
            ->join('orders as o', function ($join) {
                $join->on('o.user_id', '=', 'derived_stats.user_id')
                    ->where('o.id', '>', 0);
            })
            ->joinSub(
                DB::table('stats')
                    ->select('user_id', DB::raw('SUM(amount) as total'))
                    ->groupBy('user_id'),
                'derived_stats',
                'users.id',
                '=',
                'derived_stats.user_id'
            );

        $query->get();
        $this->assertIsArray($query->getBindings());
    }
}
