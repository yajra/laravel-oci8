<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Expression;
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

    #[Test]
    public function it_compiles_all_oracle_supported_laravel_join_variants(): void
    {
        $statsSub = DB::table('stats')->select('user_id');

        $sql = DB::table('users as u')
            ->join('orders as i', 'i.user_id', '=', 'u.id')
            ->leftJoin('orders as l', 'l.user_id', '=', 'u.id')
            ->rightJoin('orders as r', 'r.user_id', '=', 'u.id')
            ->crossJoin('stats as c')
            ->joinSub($statsSub, 'js', 'js.user_id', '=', 'u.id')
            ->leftJoinSub($statsSub, 'ljs', 'ljs.user_id', '=', 'u.id')
            ->rightJoinSub($statsSub, 'rjs', 'rjs.user_id', '=', 'u.id')
            ->crossJoinSub($statsSub, 'cjs')
            ->select('u.id')
            ->toSql();

        $normalized = strtoupper($sql);

        $this->assertStringContainsString('INNER JOIN', $normalized);
        $this->assertStringContainsString('LEFT JOIN', $normalized);
        $this->assertStringContainsString('RIGHT JOIN', $normalized);
        $this->assertStringContainsString('CROSS JOIN', $normalized);
        $this->assertStringContainsString('"JS"', $normalized);
        $this->assertStringContainsString('"LJS"', $normalized);
        $this->assertStringContainsString('"RJS"', $normalized);
        $this->assertStringContainsString('"CJS"', $normalized);
    }

    #[Test]
    public function it_extracts_alias_from_expression_with_or_without_quotes(): void
    {
        $rows = DB::table('users')
            ->join(
                new Expression('(select user_id, sum(amount) as total from "STATS" group by user_id) ds'),
                'ds.user_id',
                '=',
                'users.id'
            )
            ->join(
                new Expression('(select user_id, count(*) as order_count from "ORDERS" group by user_id) "Q_ALIAS"'),
                'Q_ALIAS.user_id',
                '=',
                'users.id'
            )
            ->select('users.id', DB::raw('ds.total as derived_total'), DB::raw('Q_ALIAS.order_count as quoted_order_count'))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
        $this->assertSame(100, (int) $rows[0]->derived_total);
        $this->assertSame(1, (int) $rows[0]->quoted_order_count);
    }

    #[Test]
    public function it_returns_null_for_expression_join_without_alias(): void
    {
        $rows = DB::table('users')
            ->join(new Expression('"ORDERS"'), 'users.id', '=', 'ORDERS.user_id')
            ->select('users.id', DB::raw('"ORDERS"."ID" as order_id'))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
        $this->assertSame(1, (int) $rows[0]->order_id);
    }

    #[Test]
    public function it_extracts_alias_from_whitespace_heavy_table_and_falls_back_to_table_name(): void
    {
        $rows = DB::table('users')
            ->join('stats as s_alias', 'users.id', '=', 's_alias.user_id')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.id', DB::raw('s_alias.amount as stat_amount'), DB::raw('orders.id as order_id'))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
        $this->assertSame(100, (int) $rows[0]->stat_amount);
        $this->assertSame(1, (int) $rows[0]->order_id);
    }

    #[Test]
    public function it_keeps_order_when_reorder_is_not_needed_or_aliases_are_unresolved(): void
    {
        $alreadyValid = DB::table('users as u')
            ->join('stats as s', 's.user_id', '=', 'u.id')
            ->join('orders as o', 'o.user_id', '=', 's.user_id')
            ->select('u.id', DB::raw('s.amount as stat_amount'), DB::raw('o.id as order_id'));

        $alreadyValidRows = $alreadyValid->get();

        $this->assertCount(1, $alreadyValidRows);
        $this->assertSame(1, (int) $alreadyValidRows[0]->id);
        $this->assertSame(100, (int) $alreadyValidRows[0]->stat_amount);
        $this->assertSame(1, (int) $alreadyValidRows[0]->order_id);

        $unresolvedBaseAlias = DB::table('users as u')
            ->join('stats as s', 's.user_id', '=', 'u.id')
            ->join('orders as o', 'o.user_id', '=', 'u.id')
            ->select('u.id', DB::raw('s.amount as stat_amount'), DB::raw('o.id as order_id'));

        $unresolvedBaseAliasRows = $unresolvedBaseAlias->get();

        $this->assertCount(1, $unresolvedBaseAliasRows);
        $this->assertSame(1, (int) $unresolvedBaseAliasRows[0]->id);
        $this->assertSame(100, (int) $unresolvedBaseAliasRows[0]->stat_amount);
        $this->assertSame(1, (int) $unresolvedBaseAliasRows[0]->order_id);
    }

    #[Test]
    public function it_reorders_when_a_join_references_a_later_alias_with_stable_topological_order(): void
    {
        $query = DB::table('users as u')
            ->join('orders as o', 'o.user_id', '=', 's.user_id')
            ->join('orders as o2', 'o2.user_id', '=', 'u.id')
            ->join('stats as s', 's.user_id', '=', 'u.id')
            ->select('u.id', DB::raw('o.id as o_id'), DB::raw('o2.id as o2_id'), DB::raw('s.amount as stat_amount'));

        $sql = strtoupper($query->toSql());
        $rows = $query->get();

        $o2Pos = strpos($sql, 'INNER JOIN "ORDERS" "O2"');
        $sPos = strpos($sql, 'INNER JOIN "STATS" "S"');
        $oPos = strpos($sql, 'INNER JOIN "ORDERS" "O"');

        $this->assertNotFalse($o2Pos);
        $this->assertNotFalse($sPos);
        $this->assertNotFalse($oPos);
        $this->assertLessThan($sPos, $o2Pos);
        $this->assertLessThan($oPos, $sPos);
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
        $this->assertSame(1, (int) $rows[0]->o_id);
        $this->assertSame(1, (int) $rows[0]->o2_id);
        $this->assertSame(100, (int) $rows[0]->stat_amount);
    }

    #[Test]
    public function it_keeps_original_join_order_when_join_dependencies_form_a_cycle(): void
    {
        $query = DB::table('users as u')
            ->join('orders as o', 'o.user_id', '=', 's.user_id')
            ->join('stats as s', 's.user_id', '=', 'o.user_id')
            ->select('u.id');

        $sql = strtoupper($query->toSql());

        $oPos = strpos($sql, 'INNER JOIN "ORDERS" "O"');
        $sPos = strpos($sql, 'INNER JOIN "STATS" "S"');

        $this->assertNotFalse($oPos);
        $this->assertNotFalse($sPos);
        $this->assertLessThan($sPos, $oPos);

        $this->expectException(QueryException::class);

        $query->get();
    }

    #[Test]
    public function it_reorders_many_plain_joins_declared_in_wrong_order(): void
    {
        $sql = DB::table('users as u')
            ->join('orders as o_dep_s', 'o_dep_s.user_id', '=', 's.user_id')
            ->join('orders as o_dep_ds', 'o_dep_ds.user_id', '=', 'ds.user_id')
            ->joinSub(DB::table('stats')->select('user_id'), 'ds', 'ds.user_id', '=', 'u.id')
            ->join('stats as s', 's.user_id', '=', 'u.id')
            ->join('orders as o_independent', 'o_independent.user_id', '=', 'u.id')
            ->toSql();

        $normalized = strtoupper($sql);

        $dsPos = strpos($normalized, '"DS"');
        $sPos = strpos($normalized, 'INNER JOIN "STATS" "S"');
        $depDsPos = strpos($normalized, 'INNER JOIN "ORDERS" "O_DEP_DS"');
        $depSPos = strpos($normalized, 'INNER JOIN "ORDERS" "O_DEP_S"');

        $this->assertNotFalse($dsPos);
        $this->assertNotFalse($sPos);
        $this->assertNotFalse($depDsPos);
        $this->assertNotFalse($depSPos);
        $this->assertLessThan($depDsPos, $dsPos);
        $this->assertLessThan($depSPos, $sPos);
    }

    #[Test]
    public function it_reorders_many_mixed_join_types_declared_in_wrong_order(): void
    {
        $sql = DB::table('users as u')
            ->leftJoin('orders as l_dep_s', 'l_dep_s.user_id', '=', 's.user_id')
            ->rightJoin('orders as r_dep_ds2', 'r_dep_ds2.user_id', '=', 'ds2.user_id')
            ->joinSub(DB::table('stats')->select('user_id'), 'ds1', 'ds1.user_id', '=', 'u.id')
            ->leftJoinSub(DB::table('orders')->select('user_id'), 'ds2', 'ds2.user_id', '=', 'u.id')
            ->join('stats as s', 's.user_id', '=', 'u.id')
            ->crossJoin('orders as c')
            ->toSql();

        $normalized = strtoupper($sql);

        $sPos = strpos($normalized, 'INNER JOIN "STATS" "S"');
        $lDepSPos = strpos($normalized, 'LEFT JOIN "ORDERS" "L_DEP_S"');
        $ds2Pos = strpos($normalized, '"DS2"');
        $rDepDs2Pos = strpos($normalized, 'RIGHT JOIN "ORDERS" "R_DEP_DS2"');

        $this->assertNotFalse($sPos);
        $this->assertNotFalse($lDepSPos);
        $this->assertNotFalse($ds2Pos);
        $this->assertNotFalse($rDepDs2Pos);
        $this->assertLessThan($lDepSPos, $sPos);
        $this->assertLessThan($rDepDs2Pos, $ds2Pos);
        $this->assertStringContainsString('CROSS JOIN "ORDERS" "C"', $normalized);
    }

    #[Test]
    public function it_executes_a_query_with_six_joins_declared_in_the_right_order(): void
    {
        $rows = DB::table('users as u')
            ->join('stats as s1', 's1.user_id', '=', 'u.id')
            ->join('orders as o1', 'o1.user_id', '=', 's1.user_id')
            ->join('stats as s2', 's2.user_id', '=', 'o1.user_id')
            ->join('orders as o2', 'o2.user_id', '=', 's2.user_id')
            ->joinSub(DB::table('stats')->select('user_id'), 'ds1', 'ds1.user_id', '=', 'u.id')
            ->join('orders as o3', 'o3.user_id', '=', 'ds1.user_id')
            ->select('u.id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
    }

    #[Test]
    public function it_executes_a_query_with_six_joins_declared_in_the_wrong_order(): void
    {
        $rows = DB::table('users as u')
            ->join('orders as o3', 'o3.user_id', '=', 'ds1.user_id')
            ->join('orders as o2', 'o2.user_id', '=', 's2.user_id')
            ->join('stats as s2', 's2.user_id', '=', 'o1.user_id')
            ->join('orders as o1', 'o1.user_id', '=', 's1.user_id')
            ->joinSub(DB::table('stats')->select('user_id'), 'ds1', 'ds1.user_id', '=', 'u.id')
            ->join('stats as s1', 's1.user_id', '=', 'u.id')
            ->select('u.id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
    }

}
