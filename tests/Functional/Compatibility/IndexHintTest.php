<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class IndexHintTest extends TestCase
{
    private const INDEX = 'compat_index_hint_email_idx';

    private const TABLE = 'compat_index_hint_items';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->markTestSkipped('PostgreSQL does not support Laravel index hints.');
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('email');
            $table->index('email', self::INDEX);
        });

        DB::table(self::TABLE)->insert([
            ['id' => 1, 'email' => 'one@example.com'],
            ['id' => 2, 'email' => 'two@example.com'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        parent::tearDown();
    }

    #[Test]
    public function it_can_use_an_index_hint(): void
    {
        $id = DB::table(self::TABLE)
            ->useIndex(self::INDEX)
            ->where('email', 'one@example.com')
            ->value('id');

        $this->assertSame(1, (int) $id);
    }

    #[Test]
    public function it_can_force_an_index_hint(): void
    {
        $id = DB::table(self::TABLE)
            ->forceIndex(self::INDEX)
            ->where('email', 'one@example.com')
            ->value('id');

        $this->assertSame(1, (int) $id);
    }

    #[Test]
    public function it_can_ignore_an_index_hint(): void
    {
        $id = DB::table(self::TABLE)
            ->ignoreIndex(self::INDEX)
            ->where('email', 'one@example.com')
            ->value('id');

        $this->assertSame(1, (int) $id);
    }
}
