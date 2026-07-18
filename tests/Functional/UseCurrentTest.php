<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class UseCurrentTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('use_current_columns');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_columns_with_current_defaults(): void
    {
        Schema::create('use_current_columns', function (Blueprint $table): void {
            $table->date('current_date')->useCurrent();
            $table->timestamp('current_timestamp')->useCurrent();
            $table->timestampTz('current_timestamp_tz')->useCurrent();
        });

        $columns = collect(Schema::getColumns('use_current_columns'));

        $this->assertStringContainsStringIgnoringCase(
            'CURRENT_DATE',
            (string) $columns->firstWhere('name', 'current_date')['default']
        );
        $this->assertStringContainsStringIgnoringCase(
            'CURRENT_TIMESTAMP',
            (string) $columns->firstWhere('name', 'current_timestamp')['default']
        );
        $this->assertStringContainsStringIgnoringCase(
            'CURRENT_TIMESTAMP',
            (string) $columns->firstWhere('name', 'current_timestamp_tz')['default']
        );
    }

    #[Test]
    public function it_can_add_a_column_with_a_current_default(): void
    {
        Schema::create('use_current_columns', function (Blueprint $table): void {
            $table->integer('id');
        });

        Schema::table('use_current_columns', function (Blueprint $table): void {
            $table->timestamp('current_timestamp')->useCurrent();
        });

        $column = collect(Schema::getColumns('use_current_columns'))
            ->firstWhere('name', 'current_timestamp');

        $this->assertNotNull($column);
        $this->assertStringContainsStringIgnoringCase('CURRENT_TIMESTAMP', (string) $column['default']);
    }
}
