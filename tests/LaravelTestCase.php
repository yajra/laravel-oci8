<?php

namespace Yajra\Oci8\Tests;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\PaginationState;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Yajra\Oci8\Tests\Concerns\InteractsWithTestDatabases;

abstract class LaravelTestCase extends BaseTestCase
{
    use InteractsWithTestDatabases;

    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOracleResolver();

        $this->db = new DB;

        if ($this->isPgsql()) {
            $this->db->addConnection($this->pgsqlConfig(), 'default');
            $this->db->addConnection(
                $this->pgsqlConfig([
                    'database' => 'second_connection',
                    'username' => 'second_connection',
                    'password' => 'second_connection',
                ]),
                'second_connection'
            );
        } elseif ($this->isMariaDb()) {
            $this->db->addConnection(
                $this->mysqlConfig(['database' => 'second_connection']),
                'default'
            );
            $this->db->addConnection($this->mysqlConfig(), 'second_connection');
        } else {
            $this->db->addConnection($this->oracleConfig(), 'default');
            $this->db->addConnection(
                $this->oracleConfig([
                    'username' => 'second_connection',
                    'password' => 'second_connection',
                ]),
                'second_connection'
            );
        }

        $this->db->getDatabaseManager()->setDefaultConnection('default');
        $this->db->bootEloquent();
        $this->db->setAsGlobal();

        $container = $this->db->getContainer();
        $container->instance('request', Request::create('/'));
        PaginationState::resolveUsing($container);

        if (method_exists($this, 'createSchema')) {
            $this->createSchema();
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->db->connection('default')->disconnect();
            $this->db->connection('second_connection')->disconnect();
        } catch (\Throwable $e) {
        }

        parent::tearDown();
    }
}
