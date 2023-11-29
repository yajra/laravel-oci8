<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Oci8\Tests\TestCase;

class SessionVarsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Configure custom sessionVars.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.oracle.sessionVars', [
            // Adds microseconds
            'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS.FF6',
        ]);
    }

    /** @test */
    public function it_can_redefine_timestamp_format()
    {
        $this->getConnection()->table('users')->truncate();
        $data = ['id' => 2, 'name' => 'Foo', 'email' => 'foo@example.com', 'created_at' => '2023-11-28 13:14:15.123456'];

        $this->getConnection()->table('users')->insert($data);

        $this->assertDatabaseCount('users', 1);
        $user = $this->getConnection()->table('users')->first();
        $this->assertSame('2023-11-28 13:14:15.123456', $user->created_at);
    }
}
