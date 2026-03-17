<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\SchemaState;

class OracleSchemaState extends SchemaState
{
    /**
     * Dump the database's schema into a file.
     *
     * @param  string  $path
     * @return void
     */
    public function dump(Connection $connection, $path)
    {
        $this->makeProcess($this->baseDumpCommand())
            ->mustRun(
                $this->output,
                array_merge(
                    $this->baseVariables($this->connection->getConfig()),
                    [
                        'LARAVEL_LOAD_PATH' => $path,
                    ]
                )
            );
    }

    /**
     * Get the base dump command arguments for Oracle as a string.
     *
     * @return string
     */
    protected function baseDumpCommand()
    {
        return 'exp "${:LARAVEL_LOAD_USER}"/"${:ORACLE_PASSWORD}"@"${:LARAVEL_LOAD_HOST}":"${:LARAVEL_LOAD_PORT}"/"${:ORACLE_SERVICE_NAME}" FILE="${:LARAVEL_LOAD_PATH}" OWNER="${:LARAVEL_LOAD_USER}" ROWS=N';
    }

    /**
     * Get the base variables for a dump / load command.
     *
     * @return array
     */
    protected function baseVariables(array $config)
    {
        $config['host'] ??= '';

        return [
            'LARAVEL_LOAD_HOST' => is_array($config['host']) ? $config['host'][0] : $config['host'],
            'LARAVEL_LOAD_PORT' => $config['port'] ?? '',
            'LARAVEL_LOAD_USER' => $config['username'],
            'ORACLE_PASSWORD' => $config['password'],
            'ORACLE_SERVICE_NAME' => $config['service_name'],
            'LARAVEL_LOAD_DATABASE' => $config['database'],
        ];
    }

    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     * @return void
     */
    public function load($path)
    {
        $command = 'imp "${:LARAVEL_LOAD_USER}"/"${:ORACLE_PASSWORD}"@"${:LARAVEL_LOAD_HOST}":"${:LARAVEL_LOAD_PORT}"/"${:ORACLE_SERVICE_NAME}" FILE="${:LARAVEL_LOAD_PATH}" FROMUSER="${:LARAVEL_LOAD_USER}" TOUSER="${:LARAVEL_LOAD_USER}"';

        $this->makeProcess($command)
            ->mustRun(
                null,
                array_merge(
                    $this->baseVariables($this->connection->getConfig()),
                    [
                        'LARAVEL_LOAD_PATH' => $path,
                    ]
                )
            );
    }

    /**
     * Get the name of the application's migration table.
     */
    protected function getMigrationTable(): string
    {
        [$schema, $table] = $this->connection->getSchemaBuilder()->parseSchemaAndTable($this->migrationTable, withDefaultSchema: true);

        return $schema.'.'.$this->connection->getTablePrefix().$table;
    }
}
