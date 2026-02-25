<?php

namespace Yajra\Oci8\Tests\Concerns;

use Illuminate\Database\Connection;
use PDO;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Oci8\Oci8Connection;

trait InteractsWithTestDatabases
{
    protected function isPgsql(): bool
    {
        return getenv('PGSQL') === 'true';
    }

    protected function isMariaDb(): bool
    {
        return getenv('MARIADB') === 'true';
    }

    protected function serverVersion(): string
    {
        return getenv('SERVER_VERSION') ?: '11g';
    }

    protected function pdoOptions(): array
    {
        return [
            PDO::ATTR_PERSISTENT => false,
        ];
    }

    protected function serviceName(): string
    {
        return getenv('SERVICE_NAME') ?: 'xe';
    }

    protected function dataBase(): string
    {
        return getenv('DATABASE') ?: 'xe';
    }

    protected function pgsqlConfig(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'postgres',
            'username' => 'postgres',
            'password' => 'postgres',
            'options' => $this->pdoOptions(),
        ], $overrides);
    }

    protected function mysqlConfig(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'root',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => $this->pdoOptions(),
        ], $overrides);
    }

    protected function oracleConfig(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'oracle',
            'host' => 'localhost',
            'port' => 1521,
            'database' => $this->dataBase(),
            'service_name' => $this->serviceName(),
            'username' => 'system',
            'password' => 'oracle',
            'server_version' => $this->serverVersion(),
        ], $overrides);
    }

    protected function registerOracleResolver(): void
    {

        Connection::resolverFor('oracle', function ($connection, $database, $prefix, $config) {
            if (! empty($config['dynamic'])) {
                ($config['dynamic'])($config);
            }

            $connector = new OracleConnector;
            $pdo = $connector->connect($config);
            $db = new Oci8Connection($pdo, $database, $prefix, $config);

            if (! empty($config['skip_session_vars'])) {
                return $db;
            }

            $sessionVars = [
                'NLS_TIME_FORMAT' => 'HH24:MI:SS',
                'NLS_DATE_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_TZ_FORMAT' => 'YYYY-MM-DD HH24:MI:SS TZH:TZM',
                'NLS_NUMERIC_CHARACTERS' => '.,',
                ...($config['sessionVars'] ?? []),
            ];

            if (isset($config['schema'])) {
                $sessionVars['CURRENT_SCHEMA'] = $config['schema'];
            }

            if (isset($config['session'])) {
                $sessionVars = array_merge($sessionVars, $config['session']);
            }

            if (isset($config['edition'])) {
                $sessionVars['EDITION'] = $config['edition'];
            }

            $db->setSessionVars($sessionVars);

            return $db;
        });
    }
}
