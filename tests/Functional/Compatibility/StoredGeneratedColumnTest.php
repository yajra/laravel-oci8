<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Oci8\Tests\TestCase;

class StoredGeneratedColumnTest extends TestCase
{
    public function test_created_stored_columns_are_maintained_on_insert_and_update(): void
    {
        $this->assertStoredColumnsAreMaintained(false);
    }

    public function test_added_stored_columns_are_maintained_on_insert_and_update(): void
    {
        $this->assertStoredColumnsAreMaintained(true);
    }

    private function assertStoredColumnsAreMaintained(bool $addToExistingTable): void
    {
        if (! $this->isPgsql() && ! $this->isMariaDb() && version_compare($this->serverVersion(), '26ai', '<')) {
            $this->markTestSkipped('Stored generated columns require Oracle 26ai or newer.');
        }

        $expression = DB::connection()->getSchemaGrammar()->wrap('amount').' * 2';

        Schema::create('stored_generated_columns', function (Blueprint $table) use ($addToExistingTable, $expression) {
            $table->integer('amount');

            if (! $addToExistingTable) {
                $table->integer('double_amount')->storedAs($expression);
            }
        });

        try {
            DB::table('stored_generated_columns')->insert(['amount' => 3]);

            if ($addToExistingTable) {
                Schema::table('stored_generated_columns', function (Blueprint $table) use ($expression) {
                    $table->integer('double_amount')->storedAs(DB::raw($expression));
                });
            }

            $this->assertDatabaseHas('stored_generated_columns', ['amount' => 3, 'double_amount' => 6]);

            DB::table('stored_generated_columns')->where('amount', 3)->update(['amount' => 5]);

            $this->assertDatabaseHas('stored_generated_columns', ['amount' => 5, 'double_amount' => 10]);

            DB::table('stored_generated_columns')->insert(['amount' => 7]);

            $this->assertDatabaseHas('stored_generated_columns', ['amount' => 7, 'double_amount' => 14]);
        } finally {
            Schema::dropIfExists('stored_generated_columns');
        }
    }
}
