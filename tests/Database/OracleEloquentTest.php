<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Query\Processors\SQLiteProcessor;
use Illuminate\Database\Query\Processors\SqlServerProcessor;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder;
use Yajra\Oci8\Query\Processors\OracleProcessor;

class OracleEloquentTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_new_base_query_builder_returns_oracle_builder_for_oracle_grammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'Oracle');

        /** @var \Illuminate\Database\Eloquent\Builder $builder */
        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(OracleBuilder::class, $builder);
        $this->assertInstanceOf(OracleGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(OracleProcessor::class, $builder->getProcessor());
    }

    protected function mockConnectionForModel($model, $database)
    {
        if ($database == 'Oracle') {
            $grammarClass = OracleGrammar::class;
            $processorClass = OracleProcessor::class;

            $oci8Connection = m::mock(Oci8Connection::class);
            $oci8Connection->shouldReceive('getSchemaPrefix')->andReturn('');
            $oci8Connection->shouldReceive('setSchemaPrefix');
            $oci8Connection->shouldReceive('getMaxLength')->andReturn(30);
            $oci8Connection->shouldReceive('setMaxLength');

            $grammar = new $grammarClass($oci8Connection);
            $processor = new $processorClass;
            $connection = m::mock(\Illuminate\Database\ConnectionInterface::class, [
                'getQueryGrammar' => $grammar,
                'getPostProcessor' => $processor,
            ]);
            $resolver = m::mock(\Illuminate\Database\ConnectionResolverInterface::class,
                ['connection' => $connection]);
            $class = $model::class;
            $class::setConnectionResolver($resolver);

            return;
        }

        $grammarClass = 'Illuminate\Database\Query\Grammars\\'.$database.'Grammar';
        $processorClass = 'Illuminate\Database\Query\Processors\\'.$database.'Processor';
        $grammar = new $grammarClass(m::mock(\Illuminate\Database\Connection::class));
        $processor = new $processorClass;
        $connection = m::mock(\Illuminate\Database\ConnectionInterface::class, [
            'getQueryGrammar' => $grammar,
            'getPostProcessor' => $processor,
        ]);
        $resolver = m::mock(\Illuminate\Database\ConnectionResolverInterface::class,
            ['connection' => $connection]);
        $class = $model::class;
        $class::setConnectionResolver($resolver);
    }

    public function test_new_base_query_builder_returns_illuminate_builder_for_sq_lite_grammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'SQLite');

        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(SQLiteGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(SQLiteProcessor::class, $builder->getProcessor());
    }

    public function test_new_base_query_builder_returns_illuminate_builder_for_mys_sql_grammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'MySql');

        $builder = $model->newQuery()->getQuery();
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(MySqlGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(MySqlProcessor::class,
            $builder->getProcessor());
    }

    public function test_new_base_query_builder_returns_illuminate_builder_for_postgres_grammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'Postgres');

        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(PostgresGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(PostgresProcessor::class,
            $builder->getProcessor());
    }

    // #########################################################################
    // HELPER FUNCTIONS
    // #########################################################################

    public function test_new_base_query_builder_returns_illuminate_builder_for_sql_server_grammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'SqlServer');

        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(SqlServerGrammar::class,
            $builder->getGrammar());
        $this->assertInstanceOf(SqlServerProcessor::class,
            $builder->getProcessor());
    }
}

class OracleEloquentStub extends \Yajra\Oci8\Eloquent\OracleEloquent {}
