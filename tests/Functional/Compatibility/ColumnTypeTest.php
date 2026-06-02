<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ColumnTypeTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('compatibility_column_types');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_and_use_portable_uncovered_column_types(): void
    {
        Schema::create('compatibility_column_types', function (Blueprint $table): void {
            $table->id();
            $table->addColumn('float', 'default_float')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->macAddress('mac_address')->nullable();
        });

        DB::table('compatibility_column_types')->insert([
            'default_float' => 123.5,
            'scheduled_at' => null,
            'ip_address' => '192.0.2.1',
            'mac_address' => '08:00:2b:01:02:03',
        ]);

        $row = DB::table('compatibility_column_types')->first();

        $this->assertEqualsWithDelta(123.5, (float) $row->default_float, 0.0001);
        $this->assertSame('192.0.2.1', $row->ip_address);
        $this->assertSame('08:00:2b:01:02:03', $row->mac_address);
        $this->assertNull($row->scheduled_at);
    }

    #[Test]
    public function it_reports_portable_uncovered_column_types_from_schema_metadata(): void
    {
        Schema::create('compatibility_column_types', function (Blueprint $table): void {
            $table->addColumn('float', 'default_float')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->macAddress('mac_address')->nullable();
        });

        $columns = collect(Schema::getColumns('compatibility_column_types'));

        $this->assertTrue($columns->contains(fn ($column) => $column['name'] === 'default_float'));
        $this->assertTrue($columns->contains(fn ($column) => $column['name'] === 'scheduled_at'));
        $this->assertTrue($columns->contains(fn ($column) => $column['name'] === 'ip_address'));
        $this->assertTrue($columns->contains(fn ($column) => $column['name'] === 'mac_address'));
    }
}
