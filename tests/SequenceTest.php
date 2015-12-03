<?php

use Mockery as m;
use Yajra\Oci8\Schema\Sequence;

class SequenceTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    /** @test */
    public function it_will_create_sequence()
    {
        $connection = $this->getConnection();
        $sequence   = new Sequence($connection);
        $connection->shouldReceive('statement')->andReturn(true);
        $success = $sequence->create('users_id_seq');
        $this->assertEquals(true, $success);
    }

    protected function getConnection()
    {
        return m::mock(Illuminate\Database\Connection::class);
    }

    /** @test */
    public function it_will_drop_sequence()
    {
        $sequence = m::mock(Sequence::class);
        $sequence->shouldReceive('drop')->andReturn(true);
        $sequence->shouldReceive('exists')->andReturn(false);
        $success = $sequence->drop('users_id_seq');
        $this->assertEquals(true, $success);
    }
}
