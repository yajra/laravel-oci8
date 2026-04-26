<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class UpdateFromTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->nullable();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->boolean('active')->default(false);
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('profiles');
        Schema::drop('users');

        parent::tearDown();
    }

    #[Test]
    public function it_can_update_rows_from_an_aliased_join(): void
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support Laravel updateFrom.');
        }

        DB::table('users')->insert([
            ['id' => 1, 'email' => 'old-1@example.com'],
            ['id' => 2, 'email' => 'old-2@example.com'],
        ]);

        DB::table('profiles')->insert([
            ['user_id' => 1, 'email' => 'new-1@example.com', 'active' => true],
            ['user_id' => 2, 'email' => 'new-2@example.com', 'active' => false],
        ]);

        $updated = DB::table('users as u')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('p.active', true)
            ->updateFrom([
                'email' => new Expression('p.email'),
            ]);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('users', ['id' => 1, 'email' => 'new-1@example.com']);
        $this->assertDatabaseHas('users', ['id' => 2, 'email' => 'old-2@example.com']);
    }
}
