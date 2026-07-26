<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class JsonConstraintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'oracle') {
            $this->markTestSkipped('CLOB-backed JSON constraint coverage is Oracle specific.');
        }

        if ($connection->isVersionBelow('12c') || $connection->isVersionAboveOrEqual('21c')) {
            $this->markTestSkipped('CLOB-backed JSON columns are used on Oracle 12c through 19c.');
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('json_constraints');

        parent::tearDown();
    }

    #[Test]
    public function it_rejects_invalid_json_in_clob_backed_json_columns(): void
    {
        Schema::create('json_constraints', function (Blueprint $table): void {
            $table->id();
            $table->json('payload');
        });

        DB::table('json_constraints')->insert([
            'payload' => '{"valid":true}',
        ]);

        $this->expectException(QueryException::class);

        DB::table('json_constraints')->insert([
            'payload' => 'invalid JSON',
        ]);
    }
}
