<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class VirtualAsTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_virtual_as');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_and_query_virtual_generated_columns(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql' && version_compare($connection->getServerVersion(), '18.0', '<')) {
            $this->markTestSkipped('PostgreSQL virtual generated columns require PostgreSQL 18 or newer.');
        }

        Schema::create('compatibility_virtual_as', function (Blueprint $table): void {
            $table->integer('base_value');
            $table->integer('double_value')->virtualAs('base_value * 2')->nullable();
        });

        DB::table('compatibility_virtual_as')->insert([
            'base_value' => 21,
        ]);

        $row = DB::table('compatibility_virtual_as')->first();

        $this->assertSame(21, (int) $row->base_value);
        $this->assertSame(42, (int) $row->double_value);
    }

    #[Test]
    public function it_reports_virtual_generated_columns_from_schema_metadata(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql' && version_compare($connection->getServerVersion(), '18.0', '<')) {
            $this->markTestSkipped('PostgreSQL virtual generated columns require PostgreSQL 18 or newer.');
        }

        Schema::create('compatibility_virtual_as', function (Blueprint $table): void {
            $table->integer('base_value');
            $table->integer('double_value')->virtualAs('base_value * 2')->nullable();
        });

        $column = collect(Schema::getColumns('compatibility_virtual_as'))
            ->firstWhere('name', 'double_value');

        $this->assertNotNull($column);
        $this->assertNotNull($column['generation']);
        $this->assertSame('virtual', $column['generation']['type']);
        $this->assertStringContainsStringIgnoringCase('base_value', (string) $column['generation']['expression']);
    }
}
