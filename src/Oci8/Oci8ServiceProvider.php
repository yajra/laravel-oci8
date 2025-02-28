<?php

namespace Yajra\Oci8;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Yajra\Oci8\Auth\OracleUserProvider;
use Yajra\Oci8\Connectors\OracleConnector as Connector;

class Oci8ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oracle.php' => config_path('oracle.php'),
        ], 'oracle');

        // Testing for existence of AuthServiceProvider before invoking it
        // prevents errors when used with laravel-zero micro-framework which
        // doesn't need auth.
        if (class_exists(\Illuminate\Auth\AuthServiceProvider::class)) {
            Auth::provider('oracle', fn ($app, array $config) => new OracleUserProvider($app['hash'], $config['model']));
        }
    }

    public function register(): void
    {
        if (file_exists(config_path('oracle.php'))) {
            $this->mergeConfigFrom(config_path('oracle.php'), 'database.connections');
        } else {
            $this->mergeConfigFrom(__DIR__.'/../config/oracle.php', 'database.connections');
        }

        Connection::resolverFor('oracle', function ($connection, $database, $prefix, $config) {
            if (! empty($config['dynamic'])) {
                call_user_func_array($config['dynamic'], [&$config]);
            }

            $connector = new Connector;
            $connection = $connector->connect($config);
            $db = new Oci8Connection($connection, $database, $prefix, $config);

            if (! empty($config['skip_session_vars'])) {
                return $db;
            }

            // set oracle session variables
            $sessionVars = [
                'NLS_TIME_FORMAT' => 'HH24:MI:SS',
                'NLS_DATE_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_TZ_FORMAT' => 'YYYY-MM-DD HH24:MI:SS TZH:TZM',
                'NLS_NUMERIC_CHARACTERS' => '.,',
                ...($config['sessionVars'] ?? []),
            ];

            // Like Postgres, Oracle allows the concept of "schema"
            if (isset($config['schema'])) {
                $sessionVars['CURRENT_SCHEMA'] = $config['schema'];
            }

            if (isset($config['session'])) {
                $sessionVars = array_merge($sessionVars, $config['session']);
            }

            if (isset($config['edition'])) {
                $sessionVars = array_merge(
                    $sessionVars,
                    ['EDITION' => $config['edition']]
                );
            }

            $db->setSessionVars($sessionVars);

            return $db;
        });
    }
}
