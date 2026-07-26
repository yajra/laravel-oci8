<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DropIfExistsTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('drop_child');
        Schema::dropIfExists('drop_parent');

        parent::tearDown();
    }

    #[Test]
    public function it_does_not_suppress_errors_for_existing_tables(): void
    {
        Schema::create('drop_parent', function (Blueprint $table): void {
            $table->integer('id')->primary();
        });

        Schema::create('drop_child', function (Blueprint $table): void {
            $table->integer('parent_id');
            $table->foreign('parent_id')->references('id')->on('drop_parent');
        });

        $this->expectException(QueryException::class);

        Schema::dropIfExists('drop_parent');
    }

    #[Test]
    public function it_suppresses_identifiers_too_long_for_pre_12cr2_databases(): void
    {
        if (! DB::connection()->isVersionBelow('12cR2')) {
            $this->markTestSkipped('Oracle 12cR2 and newer support identifiers longer than 30 bytes.');
        }

        Schema::dropIfExists('this_table_name_is_longer_than_30_bytes');

        $this->addToAssertionCount(1);
    }
}
