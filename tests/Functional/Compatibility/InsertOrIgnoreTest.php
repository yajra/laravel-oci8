<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class InsertOrIgnoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('insert_ignore_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        });

        Schema::create('insert_ignore_source', function (Blueprint $table) {
            $table->id();
            $table->string('source_email');
            $table->string('source_name');
        });

        DB::table('insert_ignore_users')->insert([
            'email' => 'existing@example.com',
            'name' => 'Existing',
        ]);

        DB::table('insert_ignore_source')->insert([
            ['source_email' => 'existing@example.com', 'source_name' => 'Existing'],
            ['source_email' => 'using@example.com', 'source_name' => 'Using'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('insert_ignore_source');
        Schema::dropIfExists('insert_ignore_users');

        parent::tearDown();
    }

    #[Test]
    public function it_returns_only_rows_inserted_while_ignoring_conflicts(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support insertOrIgnoreReturning.');
        }

        $result = DB::table('insert_ignore_users')->insertOrIgnoreReturning([
            ['email' => 'existing@example.com', 'name' => 'Existing'],
            ['email' => 'returning@example.com', 'name' => 'Returning'],
        ], ['email', 'name'], 'email');

        $rows = $result
            ->map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER))
            ->values()
            ->all();

        $this->assertSame([
            ['email' => 'returning@example.com', 'name' => 'Returning'],
        ], $rows);
    }

    #[Test]
    public function it_inserts_from_a_subquery_while_ignoring_matching_rows(): void
    {
        $inserted = DB::table('insert_ignore_users')->insertOrIgnoreUsing(
            ['email', 'name'],
            DB::table('insert_ignore_source')
                ->select('source_email', 'source_name')
                ->orderBy('id')
        );

        $this->assertSame(1, $inserted);
        $this->assertSame(
            ['existing@example.com', 'using@example.com'],
            DB::table('insert_ignore_users')->orderBy('id')->pluck('email')->all()
        );
    }
}
