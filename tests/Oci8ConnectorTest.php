<?php

use Mockery as m;

class DatabaseConnectorTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}

	public function testOptionResolution()
	{
		$connector = new Illuminate\Database\Connectors\Connector;
		$connector->setDefaultOptions(array(0 => 'foo', 1 => 'bar'));
		$this->assertEquals(array(0 => 'baz', 1 => 'bar', 2 => 'boom'), $connector->getOptions(array('options' => array(0 => 'baz', 2 => 'boom'))));
	}

  	/**
  	 * @todo add test to check connection
	 * @dataProvider OracleConnectProvider
	 */
	/***
	public function testOracleConnectCallsCreateConnectionWithProperArguments($dsn, $config)
	{
		$connector = $this->getMock('yajra\Oci8\Connectors\Oci8Connector', array('createConnection', 'getOptions'));
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
			array('oci:dbname=(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1521))(CONNECT_DATA =(SID = ORCL)))', array('tns' => '(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1521))(CONNECT_DATA =(SID = ORCL)))')),
			array('oci:dbname=(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1521))(CONNECT_DATA =(SID = ORCL)));charset=utf8', array('tns' => '(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1521))(CONNECT_DATA =(SID = ORCL)))', 'charset' => 'utf8')),
		);
	}
	*/

}
