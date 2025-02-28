<?php

namespace Yajra\Oci8\Tests\Database;

use Mockery as m;
use PDO;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\Sequence;
use Yajra\Oci8\Schema\Trigger;

class Oci8ConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_get_schema()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getConfig')->with('username')->andReturn('demo');
        $connection->shouldReceive('getSchema')->andReturn('demo');

        $this->assertSame('demo', $connection->getConfig('username'));
    }

    public function test_set_schema()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('setSchema')->with('demo')->once()->andReturn($connection);
        $connection->shouldReceive('getSchema')->once()->andReturn('demo');

        $connection->setSchema('demo');
        $this->assertSame('demo', $connection->getSchema());
    }

    public function test_get_schema_prefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSchemaPrefix')->andReturn('schema_prefix');

        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function test_set_schema_prefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('setSchemaPrefix')->with('schema_prefix')->once()->andReturn($connection);
        $connection->shouldReceive('getSchemaPrefix')->once()->andReturn('schema_prefix');

        $connection->setSchemaPrefix('schema_prefix');
        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function test_get_trigger()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getTrigger')->once()->andReturn(new Trigger($connection));

        $this->assertInstanceOf(Trigger::class, $connection->getTrigger());
    }

    public function test_get_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSequence')->once()->andReturn(new Sequence($connection));

        $this->assertInstanceOf(Sequence::class, $connection->getSequence());
    }

    public function test_create_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with('posts_id_seq')->once()->andReturn(true);
        $this->assertTrue($connection->createSequence('posts_id_seq'));
    }

    public function test_create_sequence_invalid_name()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with(null)->once()->andReturn(false);
        $this->assertFalse($connection->createSequence(null));
    }

    public function test_drop_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->shouldReceive('checkSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->checkSequence('posts_id_seq');
        $this->assertTrue($connection->dropSequence('posts_id_seq'));
    }

    public function test_drop_sequence_invalid_name()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with(null)->once()->andReturn(false);
        $connection->shouldReceive('checkSequence')->with(null)->once()->andReturn(true);
        $connection->checkSequence(null);
        $this->assertFalse($connection->dropSequence(null));
    }

    protected function getMockConnection($methods = [], $pdo = null)
    {
        $pdo = $pdo ?: new DatabaseConnectionTestMockPDO;
        $defaults = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];
        $connection = $this->getMockBuilder(\Illuminate\Database\Connection::class)
            ->onlyMethods(array_merge($defaults, $methods))
            ->setConstructorArgs([$pdo])
            ->getMock();
        $connection->enableQueryLog();

        return $connection;
    }
}

class DatabaseConnectionTestMockPDO extends PDO
{
    public function __construct() {}
}
