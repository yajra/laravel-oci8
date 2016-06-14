<?php

use Mockery as m;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Query\Processors\SQLiteProcessor;
use Illuminate\Database\Query\Processors\SqlServerProcessor;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder;
use Yajra\Oci8\Query\Processors\OracleProcessor;


/**
 * {@inheritDoc}
 */
class OracleEloquentTest extends PHPUnit_Framework_TestCase
{

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function testNewBaseQueryBuilderReturnsOracleBuilderForOracleGrammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'Oracle');

        /** @var \Illuminate\Database\Eloquent\Builder $builder */
        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(OracleBuilder::class, $builder);
        $this->assertInstanceOf(OracleGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(OracleProcessor::class,
                                $builder->getProcessor());


    }

    /**
     * @test
     */
    public function testNewBaseQueryBuilderReturnsIlliminateBuilderForSQLiteGrammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'SQLite');

        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(SQLiteGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(SQLiteProcessor::class,
                                $builder->getProcessor());
    }

    /**
     * @test
     */
    public function testNewBaseQueryBuilderReturnsIlliminateBuilderForMysSqlGrammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'MySql');

        $builder = $model->newQuery()->getQuery();
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(MySqlGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(MySqlProcessor::class,
                                $builder->getProcessor());
    }

    /**
     * @test
     */
    public function testNewBaseQueryBuilderReturnsIlliminateBuilderForPostgresGrammar()
    {
        $model = new OracleEloquentStub;
        $this->mockConnectionForModel($model, 'Postgres');

        $builder = $model->newQuery()->getQuery();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(PostgresGrammar::class, $builder->getGrammar());
        $this->assertInstanceOf(PostgresProcessor::class,
                                $builder->getProcessor());
    }

    /**
     * @test
     */
    public function testNewBaseQueryBuilderReturnsIlliminateBuilderForSqlServerGrammar()
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

    // #########################################################################
    // HELPER FUNCTIONS
    // #########################################################################
    /**
     * @param $model
     * @param $database
     */
    protected function mockConnectionForModel($model, $database)
    {
        if ($database == 'Oracle') {
            $grammarClass = OracleGrammar::class;
            $processorClass = OracleProcessor::class;
            $grammar = new $grammarClass;
            $processor = new $processorClass;
            $connection =
              m::mock('Illuminate\Database\ConnectionInterface', [
                'getQueryGrammar'  => $grammar,
                'getPostProcessor' => $processor,
              ]);
            $resolver =
              m::mock('Illuminate\Database\ConnectionResolverInterface',
                      ['connection' => $connection]);
            $class = get_class($model);
            $class::setConnectionResolver($resolver);

            return;
        }

        $grammarClass =
          'Illuminate\Database\Query\Grammars\\' . $database . 'Grammar';
        $processorClass =
          'Illuminate\Database\Query\Processors\\' . $database . 'Processor';
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $connection =
          m::mock('Illuminate\Database\ConnectionInterface', [
            'getQueryGrammar'  => $grammar,
            'getPostProcessor' => $processor,
          ]);
        $resolver =
          m::mock('Illuminate\Database\ConnectionResolverInterface',
                  ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
    }
}