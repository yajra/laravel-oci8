<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class WhereBetweenSubqueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('between_users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
        });

        Schema::create('between_scores', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('score');
            $table->string('status');
        });

        DB::table('between_users')->insert([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ]);

        DB::table('between_scores')->insert([
            ['id' => 1, 'user_id' => 1, 'score' => 15, 'status' => 'published'],
            ['id' => 2, 'user_id' => 2, 'score' => 25, 'status' => 'published'],
            ['id' => 3, 'user_id' => 3, 'score' => 15, 'status' => 'draft'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('between_scores');
        Schema::dropIfExists('between_users');

        parent::tearDown();
    }

    #[Test]
    public function it_supports_a_query_builder_subquery_in_where_between()
    {
        $score = DB::table('between_scores')
            ->select('score')
            ->whereColumn('user_id', 'between_users.id')
            ->where('status', 'published');

        $ids = DB::table('between_users')
            ->whereBetween($score, [10, 20])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $ids);
    }

    #[Test]
    public function it_supports_a_closure_subquery_in_where_between()
    {
        $ids = DB::table('between_users')
            ->whereBetween(function ($query) {
                $query->select('score')
                    ->from('between_scores')
                    ->whereColumn('user_id', 'between_users.id')
                    ->where('status', 'published');
            }, [10, 20])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $ids);
    }
}
