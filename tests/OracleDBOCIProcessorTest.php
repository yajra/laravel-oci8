<?php

use Mockery as m;

include 'mocks/OCIMocks.php';

class OracleDBOCIProcessorTest extends PHPUnit_Framework_TestCase 
{

    // defining here in case oci8 extension not installed
    protected function setUp()
    {
        if (!extension_loaded('oci8')) {
            $this->markTestSkipped(
              'The oci8 extension is not available.'
            );
        }
    }

    public function tearDown()
    {
        m::close();
    }

    public function testInsertGetIdProcessing()
    {
        $stmt = m::mock(new ProcessorTestOCIStatementStub());
        $stmt->shouldReceive('bindValue')->times(4)->withAnyArgs();
        $stmt->shouldReceive('bindParam')->once()->with(5, 0, \PDO::PARAM_INT|\PDO::PARAM_INPUT_OUTPUT, 8);
        $stmt->shouldReceive('execute')->once()->withNoArgs();

        $pdo = m::mock(new ProcessorTestOCIStub());
        $pdo->shouldReceive('prepare')->once()->with('sql')->andReturn($stmt);

        $connection = m::mock('Illuminate\Database\Connection');
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $builder = m::mock('Illuminate\Database\Query\Builder');
        $builder->shouldReceive('getConnection')->once()->andReturn($connection);

        $processor = new Jfelder\OracleDB\Query\Processors\OracleProcessor;
        
        $result = $processor->processInsertGetId($builder, 'sql', array(1, 'foo', true, null), 'id');
        $this->assertSame(0, $result);
    }

}
