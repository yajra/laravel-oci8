<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ForeignKeyConstraintsTest extends TestCase
{
    protected function tearDown(): void
    {
        $connection = DB::connection();

        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        Schema::enableForeignKeyConstraints();

        Schema::dropIfExists('fk_children');
        Schema::dropIfExists('fk_parents');

        parent::tearDown();
    }

    #[Test]
    public function it_can_disable_and_enable_foreign_key_constraints(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('fk_parents', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('fk_children', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->foreignId('parent_id');

            $foreign = $table->foreign('parent_id')
                ->references('id')
                ->on('fk_parents');

            if ($driver === 'pgsql') {
                $foreign->deferrable();
            }
        });

        DB::table('fk_parents')->insert(['id' => 1]);
        DB::table('fk_children')->insert(['parent_id' => 1]);

        if ($driver === 'pgsql') {
            DB::beginTransaction();
        }

        $this->assertTrue(Schema::disableForeignKeyConstraints());

        DB::table('fk_children')->insert(['parent_id' => 999]);

        $this->assertSame(
            1,
            DB::table('fk_children')->where('parent_id', 999)->count()
        );

        DB::table('fk_children')->where('parent_id', 999)->delete();

        $this->assertTrue(Schema::enableForeignKeyConstraints());

        if ($driver === 'pgsql' && DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    #[Test]
    public function it_enforces_foreign_key_constraints_by_default(): void
    {
        Schema::create('fk_parents', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('fk_children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('fk_parents');
        });

        $this->expectException(QueryException::class);

        DB::table('fk_children')->insert(['parent_id' => 999]);
    }

    #[Test]
    public function it_enforces_foreign_key_constraints_after_they_are_enabled(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('fk_parents', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('fk_children', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->foreignId('parent_id');

            $foreign = $table->foreign('parent_id')
                ->references('id')
                ->on('fk_parents');

            if ($driver === 'pgsql') {
                $foreign->deferrable();
            }
        });

        if ($driver === 'pgsql') {
            DB::beginTransaction();
        }

        $this->assertTrue(Schema::disableForeignKeyConstraints());
        $this->assertTrue(Schema::enableForeignKeyConstraints());

        $this->expectException(QueryException::class);

        DB::table('fk_children')->insert(['parent_id' => 999]);
    }
}
