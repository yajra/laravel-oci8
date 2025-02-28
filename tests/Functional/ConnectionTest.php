<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ConnectionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_works_on_connection_with_prefix()
    {
        $connection = $this->getConnection();

        $this->assertSame('test_', $connection->getTablePrefix());

        $first = $connection->table('users')->first();

        $this->assertSame('Record-1', $first->name);
    }

    public function test_set_date_format()
    {
        $connection = $this->getConnection();

        $connection->setDateFormat('YYYY-MM-DD');

        $date = $connection->select('select sysdate from dual');
        $format = Carbon::now()->format('Y-m-d');

        $this->assertSame($format, $date[0]->sysdate);
    }

    /**
     * Set up the environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.oracle.prefix', 'test_');
    }
}
