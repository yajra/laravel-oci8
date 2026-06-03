<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Validation\Oci8DatabasePresenceVerifier;

class Oci8DatabasePresenceVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_get_count_delegates_to_parent_for_non_oci8_connections(): void
    {
        $connection = $this->mockNonOci8Connection();
        $connectionCheckBuilder = $this->mockTableBuilder($connection);
        $countBuilder = $this->mockTableBuilder($connection);

        $countBuilder->shouldReceive('where')->once()->with('email', '=', 'jane@example.com')->andReturnSelf();
        $countBuilder->shouldReceive('where')->once()->with('id', '<>', 10)->andReturnSelf();
        $countBuilder->shouldReceive('where')->once()->with('status', 'active')->andReturnSelf();
        $countBuilder->shouldReceive('count')->once()->withNoArgs()->andReturn(3);

        $verifier = new Oci8DatabasePresenceVerifier($this->mockResolver($connectionCheckBuilder, $countBuilder));

        $this->assertSame(
            3,
            $verifier->getCount('users', 'email', 'jane@example.com', 10, 'id', ['status' => 'active'])
        );
    }

    public function test_get_multi_count_delegates_to_parent_for_non_oci8_connections(): void
    {
        $connection = $this->mockNonOci8Connection();
        $connectionCheckBuilder = $this->mockTableBuilder($connection);
        $countBuilder = $this->mockTableBuilder($connection);
        $values = ['jane@example.com', 'john@example.com'];

        $countBuilder->shouldReceive('whereIn')->once()->with('email', $values)->andReturnSelf();
        $countBuilder->shouldReceive('where')->once()->with('status', 'active')->andReturnSelf();
        $countBuilder->shouldReceive('distinct')->once()->withNoArgs()->andReturnSelf();
        $countBuilder->shouldReceive('count')->once()->with('email')->andReturn(2);

        $verifier = new Oci8DatabasePresenceVerifier($this->mockResolver($connectionCheckBuilder, $countBuilder));

        $this->assertSame(
            2,
            $verifier->getMultiCount('users', 'email', $values, ['status' => 'active'])
        );
    }

    private function mockNonOci8Connection(): ConnectionInterface
    {
        return m::mock(ConnectionInterface::class);
    }

    private function mockTableBuilder(ConnectionInterface $connection): Builder
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('useWritePdo')->once()->withNoArgs()->andReturnSelf();
        $builder->shouldReceive('getConnection')->zeroOrMoreTimes()->withNoArgs()->andReturn($connection);

        return $builder;
    }

    private function mockResolver(Builder ...$builders): ConnectionResolverInterface
    {
        $resolver = m::mock(ConnectionResolverInterface::class);
        $connection = m::mock(ConnectionInterface::class);

        $resolver->shouldReceive('connection')->times(count($builders))->with(null)->andReturn($connection);
        $connection->shouldReceive('table')->times(count($builders))->with('users')->andReturn(...$builders);

        return $resolver;
    }
}
