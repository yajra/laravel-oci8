<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class NullSafeEqualsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('compatibility_nullsafe_items', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('category')->nullable();
        });

        DB::table('compatibility_nullsafe_items')->insert([
            ['id' => 1, 'category' => null],
            ['id' => 2, 'category' => 'news'],
            ['id' => 3, 'category' => 'news'],
            ['id' => 4, 'category' => 'blog'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_nullsafe_items');

        parent::tearDown();
    }

    #[Test]
    public function it_matches_null_values()
    {
        $ids = DB::table('compatibility_nullsafe_items')
            ->whereNullSafeEquals('category', null)
            ->orderBy('id')
            ->get(['id'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $ids);
    }

    #[Test]
    public function it_matches_non_null_values()
    {
        $ids = DB::table('compatibility_nullsafe_items')
            ->whereNullSafeEquals('category', 'news')
            ->orderBy('id')
            ->get(['id'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([2, 3], $ids);
    }

    #[Test]
    public function it_matches_non_null_values_through_the_operator()
    {
        $ids = DB::table('compatibility_nullsafe_items')
            ->where('category', '<=>', 'blog')
            ->orderBy('id')
            ->get(['id'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([4], $ids);
    }

    #[Test]
    public function it_can_be_negated()
    {
        $ids = DB::table('compatibility_nullsafe_items')
            ->whereNot(fn ($query) => $query->whereNullSafeEquals('category', 'news'))
            ->orderBy('id')
            ->get(['id'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 4], $ids);
    }
}
