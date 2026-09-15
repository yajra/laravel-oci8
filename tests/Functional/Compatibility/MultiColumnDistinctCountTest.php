<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class MultiColumnDistinctCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('compatibility_distinct_counts', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('category');
            $table->string('status');
            $table->integer('enabled');
        });

        DB::table('compatibility_distinct_counts')->insert([
            ['id' => 1, 'category' => 'news', 'status' => 'draft', 'enabled' => 1],
            ['id' => 2, 'category' => 'news', 'status' => 'draft', 'enabled' => 1],
            ['id' => 3, 'category' => 'news', 'status' => 'published', 'enabled' => 1],
            ['id' => 4, 'category' => 'blog', 'status' => 'draft', 'enabled' => 1],
            ['id' => 5, 'category' => 'blog', 'status' => 'draft', 'enabled' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_distinct_counts');

        parent::tearDown();
    }

    #[Test]
    public function it_counts_distinct_column_combinations(): void
    {
        $query = DB::table('compatibility_distinct_counts as items')
            ->where('items.enabled', 1);

        $count = $this->isPgsql()
            ? $query->distinct()->count(DB::raw('(items.category, items.status)'))
            : $query->distinct(['items.category', 'items.status'])->count();

        $this->assertSame(3, $count);
    }

    #[Test]
    public function it_counts_distinct_aggregate_columns(): void
    {
        $query = DB::table('compatibility_distinct_counts')
            ->where('enabled', 1)
            ->distinct();

        $count = $this->isPgsql()
            ? $query->count(DB::raw('(category, status)'))
            : $query->count(['category', 'status']);

        $this->assertSame(3, $count);
    }

    #[Test]
    public function it_preserves_single_column_distinct_counts(): void
    {
        $count = DB::table('compatibility_distinct_counts')
            ->where('enabled', 1)
            ->distinct()
            ->count('category');

        $this->assertSame(2, $count);
    }
}
