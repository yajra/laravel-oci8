<?php

namespace Yajra\Oci8\Tests\Database;

use Mockery as m;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Schema\Sequence;

class SequenceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    #[Test]
    public function it_will_create_sequence()
    {
        $connection = $this->getConnection();
        $sequence = new Sequence($connection);
        $connection->shouldReceive('getConfig')->andReturn('');
        $connection->shouldReceive('statement')->andReturn(true);
        $connection->shouldReceive('getSchemaPrefix');
        $connection->shouldReceive('withSchemaPrefix');

        $grammar = m::mock(OracleGrammar::class);
        $grammar->shouldReceive('wrap')->andReturn('users_id_seq');

        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);

        $success = $sequence->create('users_id_seq');
        $this->assertTrue($success);
    }

    #[Test]
    public function it_can_set_sequence_name_with_schema_prefix()
    {
        $connection = $this->getConnection();
        $connection->shouldReceive('getSchemaPrefix')->andReturn('schema_prefix');
        $connection->shouldReceive('withSchemaPrefix')->andReturn('schema_prefix.users_id_seq');

        $sequence = new Sequence($connection);
        $name = $sequence->withSchemaPrefix('users_id_seq');
        $this->assertSame($name, 'schema_prefix.users_id_seq');
    }

    protected function getConnection()
    {
        return m::mock(Oci8Connection::class);
    }

    #[Test]
    public function it_will_drop_sequence()
    {
        $sequence = m::mock(Sequence::class);
        $sequence->shouldReceive('drop')->andReturn(true);
        $sequence->shouldReceive('exists')->andReturn(false);
        $success = $sequence->drop('users_id_seq');
        $this->assertTrue($success);
    }
}
