<?php

use Mockery as m;

class OracleDBProcessorTest extends PHPUnit_Framework_TestCase 
{

    public function tearDown()
    {
        m::close();
    }

    public function testInsertGetIdProcessing()
    {
        $pdo = $this->getMock('ProcessorTestPDOStub');
        $pdo->expects($this->once())->method('lastInsertId')->with($this->equalTo('id'))->will($this->returnValue('1'));
        $connection = m::mock('Illuminate\Database\Connection');
        $connection->shouldReceive('insert')->once()->with('sql', array('foo'));
        $connection->shouldReceive('getPdo')->andReturn($pdo);
        $builder = m::mock('Illuminate\Database\Query\Builder');
        $builder->shouldReceive('getConnection')->times(2)->andReturn($connection);
        $processor = new Illuminate\Database\Query\Processors\Processor;
        $result = $processor->processInsertGetId($builder, 'sql', array('foo'), 'id');
        $this->assertSame(1, $result);

        $stmt = m::mock(new ProcessorTestPDOStatementStub());
        $stmt->shouldReceive('bindValue')->once()->with(1, 1, \PDO::PARAM_INT);
        $stmt->shouldReceive('bindValue')->once()->with(2, 'foo', \PDO::PARAM_STR);
        $stmt->shouldReceive('bindValue')->once()->with(3, true, \PDO::PARAM_BOOL);
        $stmt->shouldReceive('bindValue')->once()->with(4, null, \PDO::PARAM_NULL);
        $stmt->shouldReceive('bindParam')->once()->with(5, 0, \PDO::PARAM_INT|\PDO::PARAM_INPUT_OUTPUT, 8);
        $stmt->shouldReceive('execute')->once()->withNoArgs();

        $pdo = m::mock(new ProcessorTestPDOStub());
        $pdo->shouldReceive('prepare')->once()->with('sql')->andReturn($stmt);

        $connection = m::mock('Illuminate\Database\Connection');
        $connection->shouldReceive('getPdo')->times(5)->andReturn($pdo);

        $builder = m::mock('Illuminate\Database\Query\Builder');
        $builder->shouldReceive('getConnection')->times(5)->andReturn($connection);

        $processor = new Jfelder\OracleDB\Query\Processors\OracleProcessor;
        $result = $processor->processInsertGetId($builder, 'sql', array(1, 'foo', true, null), 'id');
        $this->assertSame(0, $result);
    }

}

class ProcessorTestPDOStub extends PDO {
	public function __construct() {}
	public function lastInsertId($sequence = null) {}
	public function prepare($statement, $driver_options = array()) {}
}

class ProcessorTestPDOStatementStub extends PDOStatement {
	public function __construct() {}
        public function bindValue($parameter, $value, $data_type = 'PDO::PARAM_STR') {}
        public function bindParam($parameter, &$variable, $data_type = 'PDO::PARAM_STR', $length = null, $driver_options = null) {}
        public function execute($input_parameters = null) {}
}
