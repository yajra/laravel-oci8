<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class IncrementDecrementEachTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('increment_each_users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('votes');
            $table->integer('score');
            $table->string('updated_at')->nullable();
        });

        DB::table('increment_each_users')->insert([
            'id' => 1,
            'votes' => 10,
            'score' => 20,
            'updated_at' => 'before',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('increment_each_users');

        parent::tearDown();
    }

    #[Test]
    public function it_increments_each_column(): void
    {
        $updated = DB::table('increment_each_users')
            ->where('id', '=', 1)
            ->incrementEach(
                ['votes' => 2, 'score' => 3],
                ['updated_at' => 'after increment']
            );

        $user = DB::table('increment_each_users')->where('id', '=', 1)->first();

        $this->assertSame(1, $updated);
        $this->assertSame(12, (int) $user->votes);
        $this->assertSame(23, (int) $user->score);
        $this->assertSame('after increment', $user->updated_at);
    }

    #[Test]
    public function it_decrements_each_column(): void
    {
        $updated = DB::table('increment_each_users')
            ->where('id', '=', 1)
            ->decrementEach(
                ['votes' => 2, 'score' => 3],
                ['updated_at' => 'after decrement']
            );

        $user = DB::table('increment_each_users')->where('id', '=', 1)->first();

        $this->assertSame(1, $updated);
        $this->assertSame(8, (int) $user->votes);
        $this->assertSame(17, (int) $user->score);
        $this->assertSame('after decrement', $user->updated_at);
    }
}
