<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DatabaseQueryBuilderBitwiseOperatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('bitwise_flags', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('flags');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('bitwise_flags');

        parent::tearDown();
    }

    #[Test]
    public function test_where_supports_bitwise_operators()
    {
        foreach ($this->whereBitwiseCases() as [$operator, $values, $expected]) {
            DB::table('bitwise_flags')->truncate();
            DB::table('bitwise_flags')->insert(array_map(
                static fn (int $flags) => ['flags' => $flags],
                $values
            ));

            $results = DB::table('bitwise_flags')
                ->where('flags', $operator, 4)
                ->orderBy('id')
                ->pluck('flags')
                ->map(static fn ($flags) => (int) $flags)
                ->all();

            $this->assertSame($expected, $results, sprintf('Failed asserting where operator "%s".', $operator));
        }
    }

    #[Test]
    public function test_having_supports_bitwise_operators()
    {
        foreach ($this->havingBitwiseCases() as [$operator, $values, $expected]) {
            DB::table('bitwise_flags')->truncate();
            DB::table('bitwise_flags')->insert(array_map(
                static fn (int $flags) => ['flags' => $flags],
                $values
            ));

            $results = DB::table('bitwise_flags')
                ->select('flags')
                ->groupBy('flags')
                ->having('flags', $operator, 4)
                ->orderBy('flags')
                ->pluck('flags')
                ->map(static fn ($flags) => (int) $flags)
                ->all();

            $this->assertSame($expected, $results, sprintf('Failed asserting having operator "%s".', $operator));
        }
    }

    protected function whereBitwiseCases(): array
    {
        return [
            ['&', [1, 4, 5, 8], [4, 5]],
            ['|', [0, 1, 4], [0, 1, 4]],
            [$this->isPgsql() ? '#' : '^', [0, 1, 4], [0, 1]],
            ['<<', [0, 1, 4], [1, 4]],
            ['>>', [0, 1, 8], [8]],
        ];
    }

    protected function havingBitwiseCases(): array
    {
        return [
            ['&', [1, 4, 4, 5, 8], [4, 5]],
            ['|', [0, 0, 1, 4], [0, 1, 4]],
            [$this->isPgsql() ? '#' : '^', [0, 0, 1, 4, 4], [0, 1]],
            ['<<', [0, 0, 1, 4, 4], [1, 4]],
            ['>>', [0, 1, 8, 8], [8]],
        ];
    }
}
