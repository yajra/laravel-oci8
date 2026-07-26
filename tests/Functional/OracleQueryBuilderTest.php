<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Tests\TestCase;

class OracleQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('oracle_qb_records', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('category');
            $table->string('name');
            $table->integer('score');
        });

        DB::table('oracle_qb_records')->insert([
            ['id' => 1, 'category' => 'standard', 'name' => 'Alpha', 'score' => 10],
            ['id' => 2, 'category' => 'featured', 'name' => 'Beta', 'score' => 20],
            ['id' => 3, 'category' => 'featured', 'name' => 'Gamma', 'score' => 30],
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection() instanceof Oci8Connection) {
            $this->dropSynonymIfExists('QB_RECORDS_SYNONYM');
            $this->dropSynonymIfExists('QB_REMOTE_RECORDS');
            $this->dropSynonymIfExists('QB_SEQUENCE_SYNONYM');
            $this->oracleConnection()->getSequence()->drop('oracle_qb_sequence');
            Schema::connection('second_connection')->dropIfExists('oracle_qb_remote_records');
        }

        Schema::dropIfExists('oracle_qb_records');

        parent::tearDown();
    }

    #[Test]
    public function it_can_query_a_private_synonym_with_an_alias_and_row_value_predicate(): void
    {
        $this->requireOracle();
        $this->createSynonym('QB_RECORDS_SYNONYM', 'ORACLE_QB_RECORDS');

        $record = DB::table('qb_records_synonym as records')
            ->select('records.id', 'records.name', 'records.score')
            ->whereRowValues(
                ['records.category', 'records.score'],
                '=',
                ['featured', 20]
            )
            ->first();

        $this->assertSame(2, (int) $record->id);
        $this->assertSame('Beta', $record->name);
        $this->assertSame(20, (int) $record->score);
    }

    #[Test]
    public function it_can_paginate_through_a_private_synonym(): void
    {
        $this->requireOracle();
        $this->createSynonym('QB_RECORDS_SYNONYM', 'ORACLE_QB_RECORDS');

        $records = DB::table('qb_records_synonym')
            ->where('category', 'featured')
            ->orderBy('score')
            ->offset(1)
            ->limit(1)
            ->get();

        $this->assertCount(1, $records);
        $this->assertSame(3, (int) $records->first()->id);
        $this->assertSame('Gamma', $records->first()->name);
    }

    #[Test]
    public function it_can_query_a_schema_qualified_private_synonym(): void
    {
        $this->requireOracle();
        $this->createSynonym('QB_RECORDS_SYNONYM', 'ORACLE_QB_RECORDS');

        $schema = (string) $this->oracleConnection()->getConfig('username');
        $record = DB::table($schema.'.qb_records_synonym')
            ->where('id', 1)
            ->first();

        $this->assertSame('Alpha', $record->name);
    }

    #[Test]
    public function it_can_insert_update_and_delete_through_a_private_synonym(): void
    {
        $this->requireOracle();
        $this->createSynonym('QB_RECORDS_SYNONYM', 'ORACLE_QB_RECORDS');

        DB::table('qb_records_synonym')->insert([
            'id' => 4,
            'category' => 'standard',
            'name' => 'Delta',
            'score' => 40,
        ]);

        $updated = DB::table('qb_records_synonym as records')
            ->where('records.id', 4)
            ->update(['records.name' => 'Updated Delta']);

        $deleted = DB::table('qb_records_synonym')
            ->where('id', 1)
            ->delete();

        $this->assertSame(1, $updated);
        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('oracle_qb_records', [
            'id' => 4,
            'name' => 'Updated Delta',
        ]);
        $this->assertDatabaseMissing('oracle_qb_records', ['id' => 1]);
    }

    #[Test]
    public function it_can_query_and_modify_a_cross_schema_table_through_a_synonym(): void
    {
        $this->requireOracle();

        $connection = $this->oracleConnection();
        /** @var Oci8Connection $otherConnection */
        $otherConnection = DB::connection('second_connection');

        Schema::connection('second_connection')->create(
            'oracle_qb_remote_records',
            function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('name');
            }
        );

        $otherConnection->table('oracle_qb_remote_records')->insert([
            ['id' => 1, 'name' => 'Remote Alpha'],
            ['id' => 2, 'name' => 'Remote Beta'],
        ]);

        $defaultSchema = $this->quoteIdentifier(
            (string) $connection->getConfig('username')
        );
        $otherSchema = $this->quoteIdentifier(
            (string) $otherConnection->getConfig('username')
        );

        $otherConnection->statement(
            'grant select, insert, update, delete on "ORACLE_QB_REMOTE_RECORDS" to '.$defaultSchema
        );
        $this->createSynonym(
            'QB_REMOTE_RECORDS',
            $otherSchema.'."ORACLE_QB_REMOTE_RECORDS"'
        );

        $record = DB::table('qb_remote_records')->where('id', 2)->first();
        $updated = DB::table('qb_remote_records')
            ->where('id', 2)
            ->update(['name' => 'Updated Remote Beta']);

        $this->assertSame('Remote Beta', $record->name);
        $this->assertSame(1, $updated);
        $this->assertSame(
            'Updated Remote Beta',
            $otherConnection->table('oracle_qb_remote_records')
                ->where('id', 2)
                ->value('name')
        );
    }

    #[Test]
    public function it_can_select_next_values_from_a_sequence_synonym(): void
    {
        $this->requireOracle();

        $this->oracleConnection()->getSequence()->forceCreate('oracle_qb_sequence');
        $this->createSynonym('QB_SEQUENCE_SYNONYM', 'ORACLE_QB_SEQUENCE');

        $first = DB::query()
            ->fromRaw('dual')
            ->selectRaw('QB_SEQUENCE_SYNONYM.NEXTVAL as sequence_value')
            ->get()
            ->first();
        $second = DB::query()
            ->fromRaw('dual')
            ->selectRaw('QB_SEQUENCE_SYNONYM.NEXTVAL as sequence_value')
            ->get()
            ->first();

        $this->assertSame(1, (int) $first->sequence_value);
        $this->assertSame(2, (int) $second->sequence_value);
    }

    #[Test]
    public function it_can_round_trip_an_oracle_rowid_as_text(): void
    {
        $this->requireOracle();

        $record = DB::table('oracle_qb_records')
            ->select('id', 'name')
            ->selectRaw('ROWIDTOCHAR(ROWID) as physical_rowid')
            ->where('id', 2)
            ->first();

        $foundByRowId = DB::table('oracle_qb_records')
            ->whereRaw('ROWID = CHARTOROWID(?)', [$record->physical_rowid])
            ->first();

        $this->assertNotSame('', $record->physical_rowid);
        $this->assertSame(2, (int) $foundByRowId->id);
        $this->assertSame('Beta', $foundByRowId->name);
    }

    #[Test]
    public function it_can_bind_values_in_an_oracle_hierarchical_query(): void
    {
        $this->requireOracle();

        $levels = DB::query()
            ->fromRaw('dual connect by level <= ?', [4])
            ->selectRaw('level as hierarchy_level')
            ->get()
            ->pluck('hierarchy_level')
            ->map(fn ($level) => (int) $level)
            ->all();

        $this->assertSame([1, 2, 3, 4], $levels);
    }

    #[Test]
    public function it_can_select_oracle_functions_from_dual_with_bindings(): void
    {
        $this->requireOracle();

        $result = DB::query()
            ->fromRaw('dual')
            ->selectRaw("NVL(?, 'fallback') as nvl_value", [null])
            ->selectRaw("DECODE(?, ?, 'matched', 'missed') as decode_value", [1, 1])
            ->selectRaw(
                "SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') as current_schema"
            )
            ->first();

        $this->assertSame('fallback', $result->nvl_value);
        $this->assertSame('matched', $result->decode_value);
        $this->assertSame(
            strtoupper((string) $this->oracleConnection()->getConfig('username')),
            strtoupper((string) $result->current_schema)
        );
    }

    #[Test]
    public function it_can_select_an_oracle_analytic_row_number(): void
    {
        $this->requireOracle();

        $records = DB::table('oracle_qb_records')
            ->select('id', 'category', 'score')
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY "CATEGORY" ORDER BY "SCORE" DESC) as category_position'
            )
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [1 => 1, 2 => 2, 3 => 1],
            $records->mapWithKeys(
                fn ($record) => [(int) $record->id => (int) $record->category_position]
            )->all()
        );
    }

    #[Test]
    public function it_can_use_oracle_nowait_locking(): void
    {
        $this->requireOracle();

        $connection = $this->oracleConnection();
        $connection->beginTransaction();

        try {
            $records = DB::table('oracle_qb_records')
                ->where('id', 1)
                ->lock('for update nowait')
                ->get();

            $this->assertCount(1, $records);
            $this->assertSame('Alpha', $records->first()->name);
        } finally {
            $connection->rollBack();
        }
    }

    #[Test]
    public function it_can_lock_an_ordered_limited_query_with_skip_locked(): void
    {
        $this->requireOracle();

        $connection = $this->oracleConnection();
        $connection->beginTransaction();

        try {
            $record = DB::table('oracle_qb_records')
                ->orderBy('score', 'desc')
                ->lock('for update skip locked')
                ->first();

            $this->assertSame(3, (int) $record->id);
            $this->assertSame('Gamma', $record->name);
        } finally {
            $connection->rollBack();
        }
    }

    private function requireOracle(): void
    {
        if (! DB::connection() instanceof Oci8Connection) {
            $this->markTestSkipped('This test covers Oracle-specific QueryBuilder functionality.');
        }
    }

    private function oracleConnection(): Oci8Connection
    {
        /** @var Oci8Connection $connection */
        $connection = DB::connection();

        return $connection;
    }

    private function createSynonym(string $synonym, string $target): void
    {
        DB::statement(
            'create synonym '.$this->quoteIdentifier($synonym).' for '.$target
        );
    }

    private function dropSynonymIfExists(string $synonym): void
    {
        $quotedSynonym = $this->quoteIdentifier($synonym);

        DB::statement(
            "begin execute immediate 'drop synonym {$quotedSynonym}'; exception when others then null; end;"
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', strtoupper($identifier)).'"';
    }
}
