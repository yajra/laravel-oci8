<?php

namespace Yajra\Oci8\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Yajra\Oci8\Oci8ServiceProvider;
use Yajra\Oci8\Oci8ValidationServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateDatabase();

        $this->seedDatabase();
    }

    protected function migrateDatabase()
    {
        /** @var \Illuminate\Database\Schema\Builder $schemaBuilder */
        $schemaBuilder = $this->app['db']->connection()->getSchemaBuilder();
        if ($schemaBuilder->hasTable('users')) {
            $schemaBuilder->drop('users');
        }

        $schemaBuilder->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        if ($schemaBuilder->hasTable('jobs')) {
            $schemaBuilder->drop('jobs');
        }

        $schemaBuilder->create('jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('job_id')->nullable();
        });
    }

    protected function seedDatabase()
    {
        collect(range(1, 20))->each(function ($i) {
            /** @var User $user */
            User::query()->create([
                'name'  => 'Record-' . $i,
                'email' => 'Email-' . $i . '@example.com',
            ]);
        });
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
            'port'         => 49161,
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            Oci8ServiceProvider::class,
            Oci8ValidationServiceProvider::class,
        ];
    }

    /**
     * @param string|null $connection
     * @return \Illuminate\Database\Connection|\Yajra\Oci8\Oci8Connection
     */
    protected function getConnection($connection = null)
    {
        return parent::getConnection($connection);
    }
}
