<?php

namespace Yajra\Oci8;

use Illuminate\Support\ServiceProvider;
use Yajra\Oci8\Auth\OracleUserProvider;
use Yajra\Oci8\Connectors\OracleConnector as Connector;
use Illuminate\Support\Facades\Auth;

class Oci8ServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot Oci8 Provider
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/oracle.php' => config_path('oracle.php'),
        ], 'oracle');

        Auth::provider('oracle', function ($app, array $config) {
            return new OracleUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (file_exists(config_path('oracle.php'))) {
            $this->mergeConfigFrom(config_path('oracle.php'), 'database.connections');
        } else {
            $this->mergeConfigFrom(__DIR__ . '/../config/oracle.php', 'database.connections');
        }

        $this->app['db']->extend('oracle', function ($config) {
            $connector  = new Connector();
            $connection = $connector->connect($config);
            $db         = new Oci8Connection($connection, $config["database"], $config["prefix"], $config);

            if (! empty($config['skip_session_vars'])) {
                return $db;
            }

            // set oracle session variables
            $sessionVars = [
                'NLS_TIME_FORMAT'         => 'HH24:MI:SS',
                'NLS_DATE_FORMAT'         => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT'    => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_TZ_FORMAT' => 'YYYY-MM-DD HH24:MI:SS TZH:TZM',
                'NLS_NUMERIC_CHARACTERS'  => '.,',
            ];

            // Like Postgres, Oracle allows the concept of "schema"
            if (isset($config['schema'])) {
                $sessionVars['CURRENT_SCHEMA'] = $config['schema'];
            }
            
            if (isset($config['session'])) {
                $sessionVars = array_merge($sessionVars, $config['session']);
            }

            $db->setSessionVars($sessionVars);

            return $db;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [];
    }
}
