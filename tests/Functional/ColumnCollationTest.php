<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ColumnCollationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $maxStringSize = DB::scalar("select value from v\$parameter where name = 'max_string_size'");
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-00942')) {
                $this->markTestSkipped('Oracle column collations require access to V_$PARAMETER.');
            }

            throw $e;
        }

        if (
            DB::connection()->isVersionBelow('12cR2')
            || strtoupper((string) $maxStringSize) !== 'EXTENDED'
        ) {
            $this->markTestSkipped('Oracle column collations require Oracle 12cR2+ and MAX_STRING_SIZE=EXTENDED.');
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('collated_columns');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_a_collated_column(): void
    {
        Schema::create('collated_columns', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->collation('binary_ci');
        });

        $column = collect(Schema::getColumns('collated_columns'))
            ->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertSame('binary_ci', strtolower((string) $column['collation']));
    }

    #[Test]
    public function it_can_add_a_collated_column(): void
    {
        Schema::create('collated_columns', function (Blueprint $table): void {
            $table->id();
        });

        Schema::table('collated_columns', function (Blueprint $table): void {
            $table->string('name')->collation('binary_ci');
        });

        $column = collect(Schema::getColumns('collated_columns'))
            ->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertSame('binary_ci', strtolower((string) $column['collation']));
    }
}
