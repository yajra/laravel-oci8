<?php

use Mockery as m;

class OracleDBConnectorTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}
      	/**
	 * @dataProvider OracleConnectProvider
	 */
	public function testOracleConnectCallsCreateConnectionWithProperArguments($dsn, $config)
	{
		$connector = $this->getMock('Jfelder\OracleDB\Connectors\OracleConnector', array('createConnection', 'getOptions'));
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->will($this->returnValue(array('options')));
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(array('options')))->will($this->returnValue($connection));
		$result = $connector->connect($config);

		$this->assertTrue($result === $connection);
	}

	public function OracleConnectProvider()
	{
		return array(
			array('oci:dbname=//localhost:1521/orcl', array('host' => '//localhost', 'port' => '1521', 'database' => 'orcl')),
			array('oci:dbname=localhost:1521/orcl', array('host' => 'localhost', 'port' => '1521', 'database' => 'orcl')),
			array('oci:dbname=//localhost:1521/orcl;charset=utf8', array('host' => '//localhost', 'port' => '1521', 'database' => 'orcl', 'charset' => 'utf8')),
			array('oci:dbname=localhost:1521/orcl;charset=utf8', array('host' => 'localhost', 'port' => '1521', 'database' => 'orcl', 'charset' => 'utf8')),
			array('oci:dbname=//localhost/orcl', array('host' => '//localhost', 'database' => 'orcl')),
			array('oci:dbname=localhost/orcl', array('host' => 'localhost', 'database' => 'orcl')),
			array('oci:dbname=//localhost/orcl;charset=utf8', array('host' => '//localhost', 'database' => 'orcl', 'charset' => 'utf8')),
			array('oci:dbname=localhost/orcl;charset=utf8', array('host' => 'localhost', 'database' => 'orcl', 'charset' => 'utf8')),
			array('oci:dbname=//localhost/orcl', array('database' => '//localhost/orcl')),
			array('oci:dbname=//localhost/orcl;charset=utf8', array('database' => '//localhost/orcl', 'charset' => 'utf8')),
		);
	}

        
}