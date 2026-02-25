<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class SessionVarsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('users');

        parent::tearDown();
    }

    /**
     * Configure custom sessionVars.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.oracle.sessionVars', [
            // Adds microseconds
            'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS.FF6',
        ]);
    }

    #[Test]
    public function it_can_redefine_timestamp_format()
    {
        $data = ['id' => 2, 'name' => 'Foo', 'email' => 'foo@example.com', 'created_at' => '2023-11-28 13:14:15.123456'];

        $this->getConnection()->table('users')->insert($data);

        $this->assertDatabaseCount('users', 1);
        $user = $this->getConnection()->table('users')->first();
        $this->assertSame('2023-11-28 13:14:15.123456', $user->created_at);
    }
}
