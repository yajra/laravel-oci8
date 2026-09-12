<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class InvisibleColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'oracle') {
            $this->markTestSkipped('Invisible column coverage is Oracle specific.');
        }

        if ($connection->isVersionBelow('12c')) {
            $this->markTestSkipped('Invisible columns require Oracle 12c or newer.');
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('invisible_columns');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_an_invisible_column(): void
    {
        Schema::create('invisible_columns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('internal_note')->invisible();
        });

        DB::table('invisible_columns')->insert([
            'name' => 'visible',
            'internal_note' => 'hidden',
        ]);

        $row = DB::table('invisible_columns')->first();

        $this->assertObjectHasProperty('name', $row);
        $this->assertObjectNotHasProperty('internal_note', $row);
        $this->assertSame('hidden', DB::table('invisible_columns')->value('internal_note'));
        $this->assertTrue(Schema::hasColumn('invisible_columns', 'internal_note'));
    }

    #[Test]
    public function it_can_add_an_invisible_column(): void
    {
        Schema::create('invisible_columns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::table('invisible_columns', function (Blueprint $table): void {
            $table->string('internal_note')->nullable()->invisible();
        });

        DB::table('invisible_columns')->insert([
            'name' => 'visible',
            'internal_note' => 'hidden',
        ]);

        $row = DB::table('invisible_columns')->first();

        $this->assertObjectNotHasProperty('internal_note', $row);
        $this->assertSame('hidden', DB::table('invisible_columns')->value('internal_note'));
        $this->assertTrue(Schema::hasColumn('invisible_columns', 'internal_note'));
    }

    #[Test]
    public function it_can_change_a_column_to_invisible(): void
    {
        Schema::create('invisible_columns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('internal_note')->nullable();
        });

        DB::table('invisible_columns')->insert([
            'name' => 'visible',
            'internal_note' => 'hidden',
        ]);

        Schema::table('invisible_columns', function (Blueprint $table): void {
            $table->string('internal_note')->nullable()->invisible()->change();
        });

        $row = DB::table('invisible_columns')->first();

        $this->assertObjectNotHasProperty('internal_note', $row);
        $this->assertSame('hidden', DB::table('invisible_columns')->value('internal_note'));
        $this->assertTrue(Schema::hasColumn('invisible_columns', 'internal_note'));
    }

    #[Test]
    public function it_can_change_an_invisible_column_to_visible(): void
    {
        Schema::create('invisible_columns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('internal_note')->nullable()->invisible();
        });

        DB::table('invisible_columns')->insert([
            'name' => 'visible',
            'internal_note' => 'hidden',
        ]);

        Schema::table('invisible_columns', function (Blueprint $table): void {
            $table->string('internal_note')->nullable()->invisible(false)->change();
        });

        $row = DB::table('invisible_columns')->first();

        $this->assertObjectHasProperty('internal_note', $row);
        $this->assertSame('hidden', $row->internal_note);
        $this->assertTrue(Schema::hasColumn('invisible_columns', 'internal_note'));
    }
}
