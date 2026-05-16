<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Tests\TestCase;

class TruncateCascadeTest extends TestCase
{
    protected bool $createdSchema = false;

    protected function tearDown(): void
    {
        OracleGrammar::cascadeOnTruncate(false);
        PostgresGrammar::cascadeOnTruncate();

        if ($this->createdSchema) {
            Schema::dropIfExists('compatibility_truncate_child');
            Schema::dropIfExists('compatibility_truncate_parent');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_truncate_with_cascade_enabled()
    {
        if (! in_array(DB::connection()->getDriverName(), ['oracle', 'pgsql'], true)) {
            $this->markTestSkipped('This compatibility test only targets Oracle and PostgreSQL.');
        }

        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('TRUNCATE CASCADE is only supported by Oracle 12c and newer.');
        }

        $this->createdSchema = true;

        Schema::create('compatibility_truncate_parent', function (Blueprint $table) {
            $table->integer('id')->primary();
        });

        Schema::create('compatibility_truncate_child', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('parent_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('compatibility_truncate_parent')
                ->cascadeOnDelete();
        });

        DB::table('compatibility_truncate_parent')->insert(['id' => 1]);
        DB::table('compatibility_truncate_child')->insert(['id' => 1, 'parent_id' => 1]);

        if (DB::connection()->getDriverName() === 'oracle') {
            OracleGrammar::cascadeOnTruncate();
        } else {
            PostgresGrammar::cascadeOnTruncate();
        }

        DB::table('compatibility_truncate_parent')->truncate();

        $this->assertSame(0, DB::table('compatibility_truncate_parent')->count());
        $this->assertSame(0, DB::table('compatibility_truncate_child')->count());
    }
}
