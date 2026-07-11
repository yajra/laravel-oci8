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
        Schema::dropIfExists('group');

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

    #[Test]
    public function it_returns_column_comments_from_schema_get_columns(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->string('name')->comment('Column comment from compatibility test.');
        });

        $column = collect(Schema::getColumns('table_comments'))->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertSame('Column comment from compatibility test.', $column['comment']);
    }

    #[Test]
    public function it_returns_column_comments_for_oracle_reserved_words(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->string('group')->comment('Reserved word column comment.');
        });

        $column = collect(Schema::getColumns('table_comments'))->firstWhere('name', 'group');

        $this->assertNotNull($column);
        $this->assertSame('Reserved word column comment.', $column['comment']);
    }

    #[Test]
    public function it_returns_table_comments_for_oracle_reserved_words(): void
    {
        Schema::create('group', function ($table): void {
            $table->id();
            $table->comment('Reserved word table comment.');
        });

        $table = collect(Schema::getTables())->firstWhere('name', 'group');

        $this->assertNotNull($table);
        $this->assertSame('Reserved word table comment.', $table['comment']);
    }

    #[Test]
    public function it_can_change_table_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->comment('Original table comment.');
        });

        Schema::table('table_comments', function ($table): void {
            $table->comment('Updated table comment.');
        });

        $table = collect(Schema::getTables())->firstWhere('name', 'table_comments');

        $this->assertNotNull($table);
        $this->assertSame('Updated table comment.', $table['comment']);
    }

    #[Test]
    public function it_can_change_column_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->string('name')->comment('Original column comment.');
        });

        Schema::table('table_comments', function ($table): void {
            $table->string('name')->comment('Updated column comment.')->change();
        });

        $column = collect(Schema::getColumns('table_comments'))->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertSame('Updated column comment.', $column['comment']);
    }

    #[Test]
    public function it_can_delete_table_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->comment('Original table comment.');
        });

        Schema::table('table_comments', function ($table): void {
            $table->comment('');
        });

        $table = collect(Schema::getTables())->firstWhere('name', 'table_comments');

        $this->assertNotNull($table);
        $this->assertContains($table['comment'], [null, '']);
    }

    #[Test]
    public function it_can_delete_column_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->string('name')->comment('Original column comment.');
        });

        Schema::table('table_comments', function ($table): void {
            $table->string('name')->comment('')->change();
        });

        $column = collect(Schema::getColumns('table_comments'))->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertNull($column['comment']);
    }

    #[Test]
    public function it_can_escape_table_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->comment("Table owner's \"quoted\" `comment`.");
        });

        $table = collect(Schema::getTables())->firstWhere('name', 'table_comments');

        $this->assertNotNull($table);
        $this->assertSame("Table owner's \"quoted\" `comment`.", $table['comment']);
    }

    #[Test]
    public function it_can_escape_column_comments(): void
    {
        Schema::create('table_comments', function ($table): void {
            $table->id();
            $table->string('name')->comment("Column owner's \"quoted\" `comment`.");
        });

        $column = collect(Schema::getColumns('table_comments'))->firstWhere('name', 'name');

        $this->assertNotNull($column);
        $this->assertSame("Column owner's \"quoted\" `comment`.", $column['comment']);
    }
}
