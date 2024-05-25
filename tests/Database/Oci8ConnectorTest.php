<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Connectors\Connector;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Pdo\Oci8;

class Oci8ConnectorTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
    }

    public function testCreateConnection()
    {
        $connector = new OracleConnectorStub;
        $tns = 'Connection String';
        $config = [
            'driver' => 'oracle',
            'host' => 'host',
            'database' => 'database',
            'port' => 'port',
            'username' => 'username',
            'password' => 'password',
            'charset' => 'charset',
            'options' => [],
        ];
        $oci8 = $connector->createConnection($tns, $config, []);
        $this->assertInstanceOf(Oci8::class, $oci8);
    }

    public function testOptionResolution()
    {
        $connector = new Connector;
        $connector->setDefaultOptions([0 => 'foo', 1 => 'bar']);
        $this->assertEquals([0 => 'baz', 1 => 'bar', 2 => 'boom'],
            $connector->getOptions(['options' => [0 => 'baz', 2 => 'boom']]));
    }

    #[DataProvider('tnsDataProvider')]
    public function testOracleConnectCallsCreateConnectionWithProperArguments($dsn, $config)
    {
        $connector = $this->getMockBuilder(OracleConnector::class)
            ->onlyMethods(['createConnection', 'getOptions'])
            ->getMock();
        $connection = m::mock('PDO');
        $connector->expects($this->once())
            ->method('getOptions')
            ->with($this->equalTo($config))
            ->willReturn(['options']);
        $connector->expects($this->once())
            ->method('createConnection')
            ->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(['options']))
            ->willReturn($connection);

        if (isset($config['schema'])) {
            $connection->shouldReceive('setSchema')->andReturnSelf();
        }

        $result = $connector->connect($config);

        $this->assertTrue($result === $connection);
    }

    public static function tnsDataProvider(): array
    {
        return [
            // multiple hosts SID
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'database' => 'ORCL',
                    'tns' => '',
                ],
            ],
            // multiple hosts SID with schema
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'database' => 'ORCL',
                    'tns' => '',
                    'schema' => 'users',
                ],
            ],
            // multiple hosts load_balance no SID
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = no) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'database' => 'ORCL',
                    'tns' => '',
                    'load_balance' => 'no',
                ],
            ],
            // multiple hosts load_balance no SID with schema
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = no) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'database' => 'ORCL',
                    'tns' => '',
                    'schema' => 'users',
                    'load_balance' => 'no',
                ],
            ],
            // multiple hosts SERVICE_NAME
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'service_name' => 'ORCL',
                    'tns' => '',
                ],
            ],
            // multiple hosts SERVICE_NAME with schema
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234))(ADDRESS = (PROTOCOL = TCP)(HOST = oracle.host)(PORT = 1234)) (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = ORCL)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost, oracle.host',
                    'port' => '1234',
                    'service_name' => 'ORCL',
                    'tns' => '',
                    'schema' => 'users',
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
                [
                    'driver' => 'pdo-via-oci8',
                    'host' => 'localhost',
                    'port' => '1234',
                    'database' => 'ORCL',
                    'tns' => '',
                ],
            ],
            // using config with alias (service name)
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver' => 'oracle',
                    'host' => 'localhost',
                    'port' => '1234',
                    'database' => '',
                    'tns' => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver' => 'oci8',
                    'host' => 'localhost',
                    'port' => '1234',
                    'database' => '',
                    'tns' => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1234)) (CONNECT_DATA =(SERVICE_NAME = SID_ALIAS)))',
                [
                    'driver' => 'pdo-via-oci8',
                    'host' => 'localhost',
                    'port' => '1234',
                    'database' => '',
                    'tns' => '',
                    'service_name' => 'SID_ALIAS',
                ],
            ],
            // using tns
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'oracle',
                    'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'oci8',
                    'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                ],
            ],
            [
                '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
                [
                    'driver' => 'pdo-via-oci8',
                    'tns' => '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 4321)) (CONNECT_DATA =(SID = ORCL)))',
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

class OracleConnectorStub extends OracleConnector
{
    public function createConnection($dsn, array $config, array $options)
    {
        return new Oci8Stub($dsn, $config['username'], $config['password'], $config['options']);
    }
}

class Oci8Stub extends Oci8
{
    public function __construct($dsn, $username, $password, array $options = [])
    {
        return true;
    }
}
