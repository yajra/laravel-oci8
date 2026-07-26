<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class WhereRowValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('compatibility_row_values', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('category');
            $table->integer('category_id');
            $table->integer('score');
            $table->integer('laravel_row_value_0');
        });

        DB::table('compatibility_row_values')->insert([
            ['id' => 1, 'category' => 'featured', 'category_id' => 1, 'score' => 10, 'laravel_row_value_0' => 1],
            ['id' => 2, 'category' => 'featured', 'category_id' => 1, 'score' => 20, 'laravel_row_value_0' => 1],
            ['id' => 3, 'category' => 'standard', 'category_id' => 2, 'score' => 10, 'laravel_row_value_0' => 2],
            ['id' => 4, 'category' => 'standard', 'category_id' => 2, 'score' => 20, 'laravel_row_value_0' => 2],
            ['id' => 5, 'category' => 'archived', 'category_id' => 3, 'score' => 10, 'laravel_row_value_0' => 3],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_row_values');

        parent::tearDown();
    }

    #[Test]
    public function it_matches_equal_row_values(): void
    {
        $id = DB::table('compatibility_row_values as records')
            ->whereRowValues(
                ['records.category', 'records.score'],
                '=',
                ['featured', 20]
            )
            ->value('records.id');

        $this->assertSame(2, (int) $id);
    }

    #[Test]
    public function it_preserves_or_where_row_values_precedence(): void
    {
        $ids = DB::table('compatibility_row_values')
            ->where('id', 1)
            ->orWhereRowValues(
                ['category', 'score'],
                '=',
                ['featured', 20]
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 2], $ids);
    }

    #[Test]
    #[DataProvider('nonEqualityOperatorProvider')]
    public function it_compares_non_equal_row_values(string $operator, array $expectedIds): void
    {
        $ids = DB::table('compatibility_row_values as records')
            ->whereRowValues(
                ['records.category_id', 'records.score'],
                $operator,
                [2, 10]
            )
            ->orderBy('records.id')
            ->pluck('records.id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame($expectedIds, $ids);
    }

    #[Test]
    public function it_does_not_shadow_an_outer_table_alias(): void
    {
        $ids = DB::table('compatibility_row_values as laravel_row_values')
            ->whereRowValues(
                ['laravel_row_values.category_id', 'laravel_row_values.score'],
                '<',
                [2, 10]
            )
            ->orderBy('laravel_row_values.id')
            ->pluck('laravel_row_values.id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 2], $ids);
    }

    #[Test]
    public function it_does_not_shadow_an_unqualified_outer_column(): void
    {
        $ids = DB::table('compatibility_row_values')
            ->whereRowValues(
                ['laravel_row_value_0', 'score'],
                '<',
                [2, 10]
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 2], $ids);
    }

    #[Test]
    public function it_compares_against_an_outer_row_expression(): void
    {
        $ids = DB::table('compatibility_row_values as records')
            ->whereRowValues(
                ['records.category_id'],
                '<',
                [DB::raw('records.score')]
            )
            ->orderBy('records.id')
            ->pluck('records.id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 2, 3, 4, 5], $ids);
    }

    public static function nonEqualityOperatorProvider(): array
    {
        return [
            'not equal' => ['!=', [1, 2, 4, 5]],
            'alternate not equal' => ['<>', [1, 2, 4, 5]],
            'less than' => ['<', [1, 2]],
            'less than or equal' => ['<=', [1, 2, 3]],
            'greater than' => ['>', [4, 5]],
            'greater than or equal' => ['>=', [3, 4, 5]],
        ];
    }
}
