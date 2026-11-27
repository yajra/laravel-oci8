<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DeleteWithLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('delete_limit_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
        });

        DB::table('delete_limit_users')->insert([
            ['name' => 'Alice', 'status' => 'inactive'],
            ['name' => 'Bob', 'status' => 'active'],
            ['name' => 'Charlie', 'status' => 'inactive'],
            ['name' => 'Diana', 'status' => 'inactive'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('delete_limit_users');

        parent::tearDown();
    }

    #[Test]
    public function it_deletes_only_the_limited_rows(): void
    {
        $deleted = DB::table('delete_limit_users')
            ->where('status', '=', 'inactive')
            ->orderBy('id')
            ->limit(2)
            ->delete();

        $this->assertSame(2, $deleted);
        $this->assertSame(
            ['Bob', 'Diana'],
            DB::table('delete_limit_users')->orderBy('id')->pluck('name')->all()
        );
    }
}
