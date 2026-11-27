<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class SelectRawTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('select_raw_orders', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('name')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('select_raw_order_items', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('order_id');
            $table->integer('amount');
        });

        DB::table('select_raw_orders')->insert([
            ['id' => 1, 'user_id' => 10, 'name' => 'First', 'created_at' => '2024-01-10 12:00:00'],
            ['id' => 2, 'user_id' => 10, 'name' => null, 'created_at' => '2025-02-11 12:00:00'],
            ['id' => 3, 'user_id' => 10, 'name' => 'Third', 'created_at' => '2025-03-12 12:00:00'],
            ['id' => 4, 'user_id' => 20, 'name' => null, 'created_at' => '2026-04-13 12:00:00'],
        ]);

        DB::table('select_raw_order_items')->insert([
            ['id' => 1, 'order_id' => 1, 'amount' => 10],
            ['id' => 2, 'order_id' => 1, 'amount' => 20],
            ['id' => 3, 'order_id' => 2, 'amount' => 5],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('select_raw_order_items');
        Schema::dropIfExists('select_raw_orders');

        parent::tearDown();
    }

    #[Test]
    public function it_selects_multiple_raw_expressions_with_aliases_when_paginating()
    {
        $results = DB::table('select_raw_orders')
            ->selectRaw(
                'user_id, '
                .'SUM(CASE WHEN EXTRACT(YEAR FROM created_at) = 2024 THEN 1 ELSE 0 END) as year_2024, '
                .'SUM(CASE WHEN EXTRACT(YEAR FROM created_at) = 2025 THEN 1 ELSE 0 END) as year_2025, '
                .'SUM(CASE WHEN EXTRACT(YEAR FROM created_at) = 2026 THEN 1 ELSE 0 END) as year_2026'
            )
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->offset(0)
            ->limit(10)
            ->get();

        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertObjectNotHasProperty('rn', $result);
        }

        $results = $results
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'year_2024' => (int) $row->year_2024,
                'year_2025' => (int) $row->year_2025,
                'year_2026' => (int) $row->year_2026,
            ])
            ->all();

        $this->assertSame([
            ['user_id' => 10, 'year_2024' => 1, 'year_2025' => 2, 'year_2026' => 0],
            ['user_id' => 20, 'year_2024' => 0, 'year_2025' => 0, 'year_2026' => 1],
        ], $results);
    }

    #[Test]
    public function it_keeps_nested_commas_within_a_raw_select_expression_when_paginating()
    {
        $names = DB::table('select_raw_orders')
            ->selectRaw("id, COALESCE(name, 'Doe, John') as display_name")
            ->orderBy('id')
            ->offset(0)
            ->limit(10)
            ->pluck('display_name')
            ->all();

        $this->assertSame(['First', 'Doe, John', 'Third', 'Doe, John'], $names);
    }

    #[Test]
    public function it_selects_from_a_separate_table_inside_multiple_raw_expressions_when_paginating()
    {
        $results = DB::table('select_raw_orders')
            ->selectRaw(
                'id, (select COALESCE(SUM(select_raw_order_items.amount), 0) from select_raw_order_items '
                .'where select_raw_order_items.order_id = select_raw_orders.id) as item_total'
            )
            ->orderBy('id')
            ->offset(0)
            ->limit(10)
            ->get();

        foreach ($results as $result) {
            $this->assertObjectNotHasProperty('rn', $result);
        }

        $this->assertSame(
            [30, 5, 0, 0],
            $results->map(fn ($result) => (int) $result->item_total)->all()
        );
    }

    #[Test]
    public function it_handles_an_oracle_alternative_quoted_literal_when_paginating()
    {
        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->markTestSkipped('Alternative quoted literals are Oracle-specific.');
        }

        $result = DB::table('select_raw_orders')
            ->selectRaw("q'[It's, complicated (really)]' as description, id")
            ->orderBy('id')
            ->offset(0)
            ->limit(1)
            ->first();

        $this->assertSame("It's, complicated (really)", $result->description);
        $this->assertObjectNotHasProperty('rn', $result);
    }

    #[Test]
    public function it_ignores_comments_when_splitting_raw_expressions_for_pagination()
    {
        $result = DB::table('select_raw_orders')
            ->selectRaw("id /* comma, and parenthesis ) */, name -- comma, and parenthesis (\n, user_id")
            ->orderBy('id')
            ->offset(0)
            ->limit(1)
            ->first();

        $this->assertSame(1, (int) $result->id);
        $this->assertSame('First', $result->name);
        $this->assertSame(10, (int) $result->user_id);
        $this->assertObjectNotHasProperty('rn', $result);
    }

    #[Test]
    public function it_handles_implicit_raw_aliases_inside_a_limited_union()
    {
        $limited = DB::table('select_raw_orders')
            ->selectRaw('id order_id, user_id customer_id')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(2);

        $results = DB::table('select_raw_orders')
            ->selectRaw('id order_id, user_id customer_id')
            ->where('id', 1)
            ->unionAll($limited)
            ->orderBy('order_id')
            ->get();

        $this->assertCount(3, $results);
        $this->assertSame([1, 2, 3], $results->pluck('order_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([10, 10, 10], $results->pluck('customer_id')->map(fn ($id) => (int) $id)->all());
    }

    #[Test]
    public function it_handles_aliased_count_and_multiplication_inside_a_limited_union()
    {
        $limited = DB::table('select_raw_order_items')
            ->selectRaw('id, COUNT(*) OVER () as item_count, amount * 2 as line_total')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(2);

        $results = DB::table('select_raw_order_items')
            ->selectRaw('id, 0 as item_count, amount as line_total')
            ->where('id', 1)
            ->unionAll($limited)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $results);
        $this->assertSame([0, 2, 2], $results->pluck('item_count')->map(fn ($count) => (int) $count)->all());
        $this->assertSame([10, 40, 10], $results->pluck('line_total')->map(fn ($total) => (int) $total)->all());
    }

    #[Test]
    public function it_projects_an_unaliased_function_inside_a_limited_union()
    {
        $limited = DB::table('select_raw_orders')
            ->selectRaw('ABS(user_id)')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(2);

        $results = DB::table('select_raw_orders')
            ->selectRaw('ABS(user_id)')
            ->where('id', 1)
            ->unionAll($limited)
            ->get();

        $this->assertCount(3, $results);
        $this->assertSame(
            [10, 10, 10],
            $results->map(fn ($result) => (int) array_values((array) $result)[0])->all()
        );
    }
}
