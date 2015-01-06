<?php

use Mockery as m;
use yajra\Oci8\Connectors\OracleConnector;

class DatabaseConnectorTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}

    public function testCreateConnection()
    {
        $connector = new OracleConnectorStub();
        $tns = "Connection String";
        $config = array(
            'driver' => 'oracle',
            'host' => 'host',
            'database' => 'database',
            'port' => 'port',
            'username' => 'username',
            'password' => 'password',
            'charset' => 'charset',
            'options' => [],
        );
         $oci8 = $connector->createConnection($tns, $config, array());
         $this->assertInstanceOf('yajra\Pdo\Oci8', $oci8);
    }

	public function testOptionResolution()
	{
		$connector = new Illuminate\Database\Connectors\Connector;
		$connector->setDefaultOptions(array(0 => 'foo', 1 => 'bar'));
		$this->assertEquals(array(0 => 'baz', 1 => 'bar', 2 => 'boom'), $connector->getOptions(array('options' => array(0 => 'baz', 2 => 'boom'))));
	}

  	/**
	 * @dataProvider OracleConnectProvider
	 */
	public function testOracleConnectCallsCreateConnectionWithProperArguments($dsn, $config)
	{
		$connector = $this->getMock('yajra\Oci8\Connectors\OracleConnector', array('createConnection','getOptions'));
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->will($this->returnValue(array('options')));
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(array('options')))->will($this->returnValue($connection));

        if (isset($config['schema']))
        {
            $connection->shouldReceive('setSchema')->andReturnSelf();
        }

		$result = $connector->connect($config);

		$this->assertTrue($result === $connection);
	}

    public function OracleConnectProvider()
	{
		return array(
            // multiple hosts
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                array('driver' => 'oracle', 'host' => 'localhost, oracle.host', 'port' => '1234', 'database' => 'ORCL', 'tns' => '')),

            // multiple hosts with schema
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                array('driver' => 'oracle', 'host' => 'localhost, oracle.host', 'port' => '1234', 'database' => 'ORCL', 'tns' => '', 'schema'=>'users')),

            // using config
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'oracle', 'host' => 'localhost', 'port' => '1234', 'database' => 'ORCL', 'tns' => '')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'oci8', 'host' => 'localhost', 'port' => '1234', 'database' => 'ORCL', 'tns' => '')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'pdo-via-oci8', 'host' => 'localhost', 'port' => '1234', 'database' => 'ORCL', 'tns' => '')),

            // using config with alias (service name)
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                array('driver' => 'oracle', 'host' => 'localhost', 'port' => '1234', 'database' => '', 'tns' => '', 'service_name' => 'SID_ALIAS')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                array('driver' => 'oci8', 'host' => 'localhost', 'port' => '1234', 'database' => '', 'tns' => '', 'service_name' => 'SID_ALIAS')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                array('driver' => 'pdo-via-oci8', 'host' => 'localhost', 'port' => '1234', 'database' => '', 'tns' => '', 'service_name' => 'SID_ALIAS')),

            // using tns
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'oracle', 'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'oci8', 'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))')),
            array('(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                array('driver' => 'pdo-via-oci8', 'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))')),

            // using tnsnames.ora
            array('xe',
                array('driver' => 'oracle', 'tns' => 'xe')),
            array('xe',
                array('driver' => 'oci8', 'tns' => 'xe')),
            array('xe',
                array('driver' => 'pdo-via-oci8', 'tns' => 'xe')),
		);
	}

}
