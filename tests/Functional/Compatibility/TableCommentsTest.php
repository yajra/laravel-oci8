<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class TableCommentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('table_comments');

        parent::tearDown();
    }

    #[Test]
    public function it_returns_table_comments_from_schema_get_tables(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->comment('Table comment from compatibility test.');
        });

        $table = collect(Schema::getTables())->firstWhere('name', 'table_comments');

        $this->assertNotNull($table);
        $this->assertSame('Table comment from compatibility test.', $table['comment']);
    }
}
