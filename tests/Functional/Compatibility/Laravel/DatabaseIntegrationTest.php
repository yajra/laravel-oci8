<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseIntegrationTest extends LaravelTestCase
{
    public function test_query_executed_to_raw_sql(): void
    {
        $connection = $this->connection();
        $connection->setEventDispatcher(new Dispatcher);
        $isOracle = $connection->getDriverName() === 'oracle';
        $sql = $isOracle ? 'select ? from dual' : 'select ?';
        $expectedRawSql = $isOracle ? 'select 1 from dual' : 'select 1';

        $connection->listen(function (QueryExecuted $query) use (&$queryExecuted): void {
            $queryExecuted = $query;
        });

        $connection->select($sql, [true]);

        $this->assertInstanceOf(QueryExecuted::class, $queryExecuted);
        $this->assertSame($sql, $queryExecuted->sql);
        $this->assertSame([true], $queryExecuted->bindings);
        $this->assertSame($expectedRawSql, $queryExecuted->toRawSql());
    }

    /**
     * Get a database connection instance.
     *
     * @return Connection
     */
    protected function connection($connection = 'default')
    {
        return Eloquent::getConnectionResolver()->connection($connection);
    }
}
