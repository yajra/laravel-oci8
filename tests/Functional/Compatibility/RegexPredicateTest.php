<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class RegexPredicateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('compatibility_regex_items', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('code')->nullable();
        });

        DB::table('compatibility_regex_items')->insert([
            ['id' => 1, 'code' => 'alpha-100'],
            ['id' => 2, 'code' => 'beta-200'],
            ['id' => 3, 'code' => 'gamma'],
            ['id' => 4, 'code' => 'ALPHA-300'],
            ['id' => 5, 'code' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_regex_items');

        parent::tearDown();
    }

    #[Test]
    public function it_matches_a_regular_expression(): void
    {
        $ids = DB::table('compatibility_regex_items')
            ->where('code', $this->isMariaDb() ? 'regexp' : '~', '[[:digit:]]+$')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 2, 4], $ids);
    }

    #[Test]
    public function it_negates_a_regular_expression(): void
    {
        $ids = DB::table('compatibility_regex_items')
            ->where('code', $this->isMariaDb() ? 'not regexp' : '!~', '[[:digit:]]+$')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([3], $ids);
    }

    #[Test]
    public function it_matches_a_regular_expression_case_insensitively(): void
    {
        $operator = $this->isMariaDb() ? 'regexp' : '~*';
        $ids = DB::table('compatibility_regex_items')
            ->where('code', $operator, '^alpha-')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1, 4], $ids);
    }

    #[Test]
    public function it_matches_a_regular_expression_case_sensitively(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not provide a portable case-sensitive regex operator.');
        }

        $ids = DB::table('compatibility_regex_items')
            ->where('code', '~', '^alpha-')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $ids);
    }
}
