<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testCreateSequence()
    {
        $connection = m::mock(Yajra\Oci8\Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with('posts_id_seq')->once()->andReturn(true);
        $this->assertEquals(true, $connection->createSequence('posts_id_seq'));
    }

    public function testCreateSequenceInvalidName()
    {
        $connection = m::mock(Yajra\Oci8\Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with(null)->once()->andReturn(false);
        $this->assertEquals(false, $connection->createSequence(null));
    }

    public function testDropSequence()
    {
        $connection = m::mock(Yajra\Oci8\Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->shouldReceive('checkSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->checkSequence('posts_id_seq');
        $this->assertEquals(true, $connection->dropSequence('posts_id_seq'));
    }

    public function testDropSequenceInvalidName()
    {
        $connection = m::mock(Yajra\Oci8\Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with(null)->once()->andReturn(false);
        $connection->shouldReceive('checkSequence')->with(null)->once()->andReturn(true);
        $connection->checkSequence(null);
        $this->assertEquals(false, $connection->dropSequence(null));
    }

    protected function getMockConnection($methods = [], $pdo = null)
    {
        $pdo        = $pdo ?: new DatabaseConnectionTestMockPDO;
        $defaults   = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];
        $connection = $this->getMockBuilder('Illuminate\Database\Connection')->setMethods(array_merge($defaults,
            $methods))->setConstructorArgs([$pdo])->getMock();
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
