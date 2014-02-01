<?php

defined('SQLT_INT') or define('SQLT_INT', 3);
defined('SQLT_CHR') or define('SQLT_CHR', 1);
defined('OCI_FETCHSTATEMENT_BY_ROW') or define('OCI_FETCHSTATEMENT_BY_ROW', 32);
defined('OCI_ASSOC') or define('OCI_ASSOC', 1);
defined('OCI_COMMIT_ON_SUCCESS') or define('OCI_COMMIT_ON_SUCCESS', 32);
defined('OCI_NO_AUTO_COMMIT') or define('OCI_NO_AUTO_COMMIT', 0);


use Mockery as m;

class OracleDBProcessorTest extends PHPUnit_Framework_TestCase 
{

    public function tearDown()
    {
        m::close();
    }

    public function testPDOInsertGetIdProcessing()
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
    }

    public function testOCIInsertGetIdProcessing()
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

class ProcessorTestPDOStub extends PDO {
	public function __construct() {}
	public function lastInsertId($sequence = null) {}
	public function prepare($statement, $driver_options = array()) {}
}

class ProcessorTestOCIStub extends Jfelder\OracleDB\OCI_PDO\OCI {
	public function __construct() {}
	public function __destruct() {}
	public function prepare($statement, $driver_options = array()) {}
}

class ProcessorTestOCIStatementStub extends Jfelder\OracleDB\OCI_PDO\OCIStatement {
	public function __construct() {}
	public function __destruct() {}
        public function bindValue($parameter, $value, $data_type = 'PDO::PARAM_STR') {}
        public function bindParam($parameter, &$variable, $data_type = 'PDO::PARAM_STR', $length = null, $driver_options = null) {}
        public function execute($input_parameters = null) {}
}
