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

    protected function migrateDatabase(): void
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

        if ($schemaBuilder->hasTable('multi_blobs')) {
            $schemaBuilder->drop('multi_blobs');
        }

        $schemaBuilder->create('multi_blobs', function (Blueprint $table) {
            $table->increments('id');
            $table->binary('blob_1')->nullable();
            $table->binary('blob_2')->nullable();
            $table->integer('status')->nullable();
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

        if ($schemaBuilder->hasTable('json_test')) {
            $schemaBuilder->drop('json_test');
        }

        $schemaBuilder->create('json_test', function (Blueprint $table) {
            $table->id('id');
            $table->json('options');
        });
    }

    protected function seedDatabase(): void
    {
        collect(range(1, 20))->each(function ($i) {
            /** @var User $user */
            User::query()->create([
                'name' => 'Record-'.$i,
                'email' => 'Email-'.$i.'@example.com',
            ]);
        });
    }

    /**
     * Set up the environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.debug', true);

        /* this allows to run specific tests with pgsql to check for compatibility */
        if (getenv('PGSQL') === 'true') {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'postgres',
                'username' => 'postgres',
                'password' => 'postgres',
            ]);
            $app['config']->set('database.connections.oracle.server_version', getenv('SERVER_VERSION') ? getenv('SERVER_VERSION') : '11g');
        } else {
            $app['config']->set('database.default', 'oracle');
            $app['config']->set('database.connections.oracle', [
                'driver' => 'oracle',
                'host' => 'localhost',
                'database' => 'xe',
                'service_name' => 'xe',
                'username' => 'system',
                'password' => 'oracle',
                'port' => 1521,
                'server_version' => getenv('SERVER_VERSION') ? getenv('SERVER_VERSION') : '11g',
            ]);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            Oci8ServiceProvider::class,
            Oci8ValidationServiceProvider::class,
        ];
    }
}
