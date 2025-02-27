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
    public function tearDown(): void
    {
        m::close();
    }

    public function testGetSchema()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getConfig')->with('username')->andReturn('demo');
        $connection->shouldReceive('getSchema')->andReturn('demo');

        $this->assertSame('demo', $connection->getConfig('username'));
    }

    public function testSetSchema()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('setSchema')->with('demo')->once()->andReturn($connection);
        $connection->shouldReceive('getSchema')->once()->andReturn('demo');

        $connection->setSchema('demo');
        $this->assertSame('demo', $connection->getSchema());
    }

    public function testGetSchemaPrefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSchemaPrefix')->andReturn('schema_prefix');

        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function testSetSchemaPrefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('setSchemaPrefix')->with('schema_prefix')->once()->andReturn($connection);
        $connection->shouldReceive('getSchemaPrefix')->once()->andReturn('schema_prefix');

        $connection->setSchemaPrefix('schema_prefix');
        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function testGetTrigger()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getTrigger')->once()->andReturn(new Trigger($connection));

        $this->assertInstanceOf(Trigger::class, $connection->getTrigger());
    }

    public function testGetSequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSequence')->once()->andReturn(new Sequence($connection));

        $this->assertInstanceOf(Sequence::class, $connection->getSequence());
    }

    public function testCreateSequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with('posts_id_seq')->once()->andReturn(true);
        $this->assertTrue($connection->createSequence('posts_id_seq'));
    }

    public function testCreateSequenceInvalidName()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with(null)->once()->andReturn(false);
        $this->assertFalse($connection->createSequence(null));
    }

    public function testDropSequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->shouldReceive('checkSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->checkSequence('posts_id_seq');
        $this->assertTrue($connection->dropSequence('posts_id_seq'));
    }

    public function testDropSequenceInvalidName()
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
        $connection = $this->getMockBuilder('Illuminate\Database\Connection')
                           ->onlyMethods(array_merge($defaults, $methods))
                           ->setConstructorArgs([$pdo])
                           ->getMock();
        $connection->enableQueryLog();

        return $connection;
    }
}

class DatabaseConnectionTestMockPDO extends PDO
{
    public function __construct()
    {
    }
}
