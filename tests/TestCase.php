<?php

namespace Yajra\Oci8\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Yajra\Oci8\Oci8ServiceProvider;
use Yajra\Oci8\Oci8ValidationServiceProvider;
use Yajra\Oci8\Tests\Concerns\InteractsWithTestDatabases;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithTestDatabases;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.debug', true);

        if ($this->isPgsql()) {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', $this->pgsqlConfig());

            return;
        }

        if ($this->isMariaDb()) {
            $app['config']->set('database.default', 'mariadb');
            $app['config']->set('database.connections.mariadb', $this->mariadbConfig([
                'driver' => 'mariadb',
            ]));

            return;
        }

        $app['config']->set('database.default', 'oracle');
        $app['config']->set('database.connections.oracle', $this->oracleConfig());
        $app['config']->set('database.connections.second_connection', $this->oracleConfig([
            'username' => $this->secondOracleUsername(),
            'password' => $this->secondOraclePassword(),
        ]));
    }

    protected function getPackageProviders($app): array
    {
        return [
            Oci8ServiceProvider::class,
            Oci8ValidationServiceProvider::class,
        ];
    }
}
