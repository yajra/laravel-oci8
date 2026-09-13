<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Query\OracleBuilder;
use Yajra\Oci8\Tests\TestCase;

class SpatialQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'oracle') {
            $this->markTestSkipped('Oracle Spatial query coverage requires Oracle.');
        }

        Schema::dropIfExists('spatial_query_places');

        DB::table('user_sdo_geom_metadata')
            ->where('table_name', 'SPATIAL_QUERY_PLACES')
            ->where('column_name', 'SHAPE')
            ->delete();

        Schema::create('spatial_query_places', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->geometry('shape');
        });

        DB::statement(<<<'SQL'
            insert into user_sdo_geom_metadata (table_name, column_name, diminfo, srid)
            values (
                'SPATIAL_QUERY_PLACES',
                'SHAPE',
                mdsys.sdo_dim_array(
                    mdsys.sdo_dim_element('X', -1000, 1000, 0.005),
                    mdsys.sdo_dim_element('Y', -1000, 1000, 0.005)
                ),
                null
            )
            SQL);

        Schema::table('spatial_query_places', function (Blueprint $table) {
            $table->spatialIndex('shape', 'spatial_query_places_shape_idx');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('spatial_query_places');

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::table('user_sdo_geom_metadata')
                ->where('table_name', 'SPATIAL_QUERY_PLACES')
                ->where('column_name', 'SHAPE')
                ->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function it_writes_and_serializes_wkt_geometry_values(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('spatial_query_places');
        $query->insert([
            'id' => 1,
            'shape' => $query->spatialValue('POINT (3 4)'),
        ]);

        $result = $query->selectSpatialAsText('shape', 'wkt')->first();

        $this->assertNotNull($result);
        $this->assertStringContainsString('POINT', strtoupper((string) $result->wkt));
    }

    #[Test]
    public function it_selects_filters_and_orders_by_spatial_distance(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('spatial_query_places');
        $query->insert([
            ['id' => 1, 'shape' => $query->spatialValue('POINT (0 0)')],
            ['id' => 2, 'shape' => $query->spatialValue('POINT (3 4)')],
            ['id' => 3, 'shape' => $query->spatialValue('POINT (10 10)')],
        ]);

        $results = $query->select('id')
            ->selectSpatialDistance('shape', 'POINT (0 0)', 'distance')
            ->whereSpatialWithinDistance('shape', 'POINT (0 0)', 5)
            ->orderBySpatialDistance('shape', 'POINT (0 0)')
            ->get();

        $this->assertSame([1, 2], $results->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertEqualsWithDelta(5.0, (float) $results[1]->distance, 0.000001);
    }

    #[Test]
    public function it_filters_intersections_and_nearest_neighbors(): void
    {
        /** @var OracleBuilder $query */
        $query = DB::table('spatial_query_places');
        $query->insert([
            ['id' => 1, 'shape' => $query->spatialValue('POINT (0 0)')],
            ['id' => 2, 'shape' => $query->spatialValue('POINT (3 4)')],
            ['id' => 3, 'shape' => $query->spatialValue('POINT (10 10)')],
        ]);

        $intersections = $query->whereSpatialIntersects('shape', 'POINT (0 0)')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $nearest = DB::table('spatial_query_places');
        /** @var OracleBuilder $nearest */
        $nearestIds = $nearest->whereSpatialNearestTo('shape', 'POINT (0 0)', 2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([1], $intersections);
        $this->assertSame([1, 2], $nearestIds);
    }
}
