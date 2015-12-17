<?php

use Mockery as m;

class DatabaseConnectorTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testCreateConnection()
    {
        $connector = new OracleConnectorStub();
        $tns       = "Connection String";
        $config    = [
            'driver'   => 'oracle',
            'host'     => 'host',
            'database' => 'database',
            'port'     => 'port',
            'username' => 'username',
            'password' => 'password',
            'charset'  => 'charset',
            'options'  => [],
        ];
        $oci8      = $connector->createConnection($tns, $config, []);
        $this->assertInstanceOf(Yajra\Pdo\Oci8::class, $oci8);
    }

    public function testOptionResolution()
    {
        $connector = new Illuminate\Database\Connectors\Connector;
        $connector->setDefaultOptions([0 => 'foo', 1 => 'bar']);
        $this->assertEquals([0 => 'baz', 1 => 'bar', 2 => 'boom'],
            $connector->getOptions(['options' => [0 => 'baz', 2 => 'boom']]));
    }

    /**
     * @dataProvider OracleConnectProvider
     * @param $dsn
     * @param $config
     */
    public function testOracleConnectCallsCreateConnectionWithProperArguments($dsn, $config)
    {
        $connector  = $this->getMock(Yajra\Oci8\Connectors\OracleConnector::class, ['createConnection', 'getOptions']);
        $connection = m::mock('stdClass');
        $connector->expects($this->once())
                  ->method('getOptions')
                  ->with($this->equalTo($config))
                  ->will($this->returnValue(['options']));
        $connector->expects($this->once())
                  ->method('createConnection')
                  ->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(['options']))
                  ->will($this->returnValue($connection));

        if (isset($config['schema'])) {
            $connection->shouldReceive('setSchema')->andReturnSelf();
        }

        $result = $connector->connect($config);

        $this->assertTrue($result === $connection);
    }

    public function OracleConnectProvider()
    {
        return [
            // multiple hosts
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                [
                    'driver'   => 'oracle',
                    'host'     => 'localhost, oracle.host',
                    'port'     => '1234',
                    'database' => 'ORCL',
                    'tns'      => '',
                ],
            ],
            // multiple hosts with schema
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                [
                    'driver'   => 'oracle',
                    'host'     => 'localhost, oracle.host',
                    'port'     => '1234',
                    'database' => 'ORCL',
                    'tns'      => '',
                    'schema'   => 'users',
                ],
            ],
            // using config
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                ['driver' => 'oracle', 'host' => 'localhost', 'port' => '1234', 'database' => 'ORCL', 'tns' => ''],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                ['driver' => 'oci8', 'host' => 'localhost', 'port' => '1234', 'database' => 'ORCL', 'tns' => ''],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SID = ORCL)))',
                ['driver'   => 'pdo-via-oci8',
                 'host'     => 'localhost',
                 'port'     => '1234',
                 'database' => 'ORCL',
                 'tns'      => '',
                ],
            ],
            // using config with alias (service name)
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver'       => 'oracle',
                    'host'         => 'localhost',
                    'port'         => '1234',
                    'database'     => '',
                    'tns'          => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver'       => 'oci8',
                    'host'         => 'localhost',
                    'port'         => '1234',
                    'database'     => '',
                    'tns'          => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver'       => 'pdo-via-oci8',
                    'host'         => 'localhost',
                    'port'         => '1234',
                    'database'     => '',
                    'tns'          => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            // using tns
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'tns'    => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'oci8',
                    'tns'    => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'pdo-via-oci8',
                    'tns'    => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                ],
            ],
            // using tnsnames.ora
            [
                'xe',
                ['driver' => 'oracle', 'tns' => 'xe'],
            ],
            [
                'xe',
                ['driver' => 'oci8', 'tns' => 'xe'],
            ],
            [
                'xe',
                ['driver' => 'pdo-via-oci8', 'tns' => 'xe'],
            ],
        ];
    }
}
