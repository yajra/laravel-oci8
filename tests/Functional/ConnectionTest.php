<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Oci8\Tests\TestCase;

class ConnectionTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_works_on_connection_with_prefix()
    {
        $connection = $this->getConnection();

        $this->assertSame('test_', $connection->getTablePrefix());

        $first = $connection->table('users')->first();

        $this->assertSame('Record-1', $first->name);
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'oracle');
        $app['config']->set('database.connections.oracle', [
            'driver'       => 'oracle',
            'host'         => 'localhost',
            'database'     => 'xe',
            'service_name' => 'xe',
            'username'     => 'system',
            'password'     => 'oracle',
            'prefix'       => 'test_',
            'port'         => 49161,
        ]);
    }
}
