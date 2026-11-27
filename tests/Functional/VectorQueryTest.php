<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Query\OracleBuilder;
use Yajra\Oci8\Tests\TestCase;

class VectorQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'oracle'
            || ! DB::connection()->isVersionAboveOrEqual('23c')) {
            $this->markTestSkipped('Native vector queries require Oracle 23ai or newer.');
        }

        Schema::dropIfExists('vector_query_documents');

        Schema::create('vector_query_documents', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->vector('embedding', 3);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vector_query_documents');

        parent::tearDown();
    }

    #[Test]
    public function it_inserts_updates_and_upserts_vector_values(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('vector_query_documents');
        $query->insert([
            'id' => 1,
            'embedding' => $query->vectorValue([1, 0, 0]),
        ]);

        $query->where('id', 1)->update([
            'embedding' => $query->vectorValue([0, 1, 0]),
        ]);

        $query->upsert([
            'id' => 1,
            'embedding' => $query->vectorValue([0, 0, 1]),
        ], 'id');

        $distance = $query->selectVectorDistance('embedding', [0, 0, 1], 'distance')
            ->first();

        $this->assertNotNull($distance);
        $this->assertEqualsWithDelta(0.0, (float) $distance->distance, 0.000001);
    }

    #[Test]
    public function it_filters_selects_and_orders_by_vector_distance(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('vector_query_documents');
        $query->insert([
            ['id' => 1, 'embedding' => $query->vectorValue([1, 0, 0])],
            ['id' => 2, 'embedding' => $query->vectorValue([0.9, 0.1, 0])],
            ['id' => 3, 'embedding' => $query->vectorValue([0, 1, 0])],
        ]);

        $results = $query->select('id')
            ->selectVectorDistance('embedding', [1, 0, 0], 'distance')
            ->whereVectorDistanceLessThan('embedding', [1, 0, 0], 0.1)
            ->orderByVectorDistance('embedding', [1, 0, 0])
            ->get();

        $this->assertSame([1, 2], $results->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertEqualsWithDelta(0.0, (float) $results[0]->distance, 0.000001);
    }

    #[Test]
    public function it_uses_explicit_vector_distance_metrics(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('vector_query_documents');
        $query->insert([
            'id' => 1,
            'embedding' => $query->vectorValue([1, 2, 3]),
        ]);

        $result = $query->selectVectorDistance(
            'embedding', [4, 6, 3], 'distance', 'euclidean'
        )->first();

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(5.0, (float) $result->distance, 0.000001);
    }
}
