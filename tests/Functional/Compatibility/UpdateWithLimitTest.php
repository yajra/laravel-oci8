<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class UpdateWithLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('update_limit_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status');
        });

        DB::table('update_limit_users')->insert([
            ['name' => 'Alice', 'status' => 'inactive'],
            ['name' => 'Bob', 'status' => 'active'],
            ['name' => 'Charlie', 'status' => 'inactive'],
            ['name' => 'Diana', 'status' => 'inactive'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('update_limit_users');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_only_the_limited_rows(): void
    {
        $updated = DB::table('update_limit_users')
            ->where('status', 'inactive')
            ->orderBy('id')
            ->limit(2)
            ->update(['name' => 'Updated']);

        $this->assertSame(2, $updated);
        $this->assertSame(
            ['Updated', 'Bob', 'Updated', 'Diana'],
            DB::table('update_limit_users')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_updates_only_the_requested_page(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support offsets on updates.');
        }

        $updated = DB::table('update_limit_users')
            ->where('status', 'inactive')
            ->orderBy('id')
            ->offset(1)
            ->limit(2)
            ->update(['name' => 'Updated']);

        $this->assertSame(2, $updated);
        $this->assertSame(
            ['Alice', 'Bob', 'Updated', 'Updated'],
            DB::table('update_limit_users')->orderBy('id')->pluck('name')->all()
        );
    }
}
