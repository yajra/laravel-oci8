<?php

namespace Yajra\Oci8\Tests\Database;

use Mockery as m;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\Sequence;

class SequenceTest extends TestCase
{
    public function tearDown(): void
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

        $success = $sequence->create('users_id_seq');
        $this->assertEquals(true, $success);
    }

    #[Test]
    public function it_can_wrap_sequence_name_with_schema_prefix()
    {
        $connection = $this->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('schema_prefix');

        $sequence = new Sequence($connection);
        $name = $sequence->wrapSchema('users_id_seq');
        $this->assertEquals($name, 'schema_prefix.users_id_seq');
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
        $this->assertEquals(true, $success);
    }
}
