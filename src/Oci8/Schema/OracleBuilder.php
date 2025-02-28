<?php

namespace Yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Yajra\Oci8\Oci8Connection;

/**
 * @property \Yajra\Oci8\Oci8Connection $connection
 * @property \Yajra\Oci8\Schema\OracleGrammar $grammar
 */
class OracleBuilder extends Builder
{
    public OracleAutoIncrementHelper $helper;

    public Comment $comment;

    /**
     * @var \Yajra\Oci8\Schema\OraclePreferences
     */
    public $ctxDdlPreferences;

    public function __construct(Oci8Connection $connection)
    {
        parent::__construct($connection);
        $this->helper = new OracleAutoIncrementHelper($connection);
        $this->comment = new Comment($connection);
        $this->ctxDdlPreferences = new OraclePreferences($connection);
    }

    /**
     * Create a new table on the schema.
     *
     * @param  string  $table
     */
    public function create($table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        $this->ctxDdlPreferences->createPreferences($blueprint);

        $this->build($blueprint);

        $this->comment->setComments($blueprint);

        $this->helper->createAutoIncrementObjects($blueprint, $table);
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     */
    protected function createBlueprint($table, ?Closure $callback = null): OracleBlueprint
    {
        return new OracleBlueprint($this->connection, $table, $callback);
    }

    /**
     * Changes an existing table on the schema.
     *
     * @param  string  $table
     */
    public function table($table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);

        $callback($blueprint);

        foreach ($blueprint->getCommands() as $command) {
            if ($command->get('name') == 'drop') {
                $this->helper->dropAutoIncrementObjects($table);
            }
        }

        $this->build($blueprint);

        $this->comment->setComments($blueprint);
    }

    /**
     * Drop a table from the schema.
     *
     * @param  string  $table
     */
    public function drop($table): void
    {
        $this->helper->dropAutoIncrementObjects($table);
        $this->ctxDdlPreferences->dropPreferencesByTable($table);
        parent::drop($table);
    }

    /**
     * Drop all tables from the database.
     */
    public function dropAllTables(): void
    {
        $this->ctxDdlPreferences->dropAllPreferences();
        $this->connection->statement($this->grammar->compileDropAllTables());
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @param  string  $table
     */
    public function dropIfExists($table): void
    {
        $this->helper->dropAutoIncrementObjects($table);
        $this->ctxDdlPreferences->dropPreferencesByTable($table);
        parent::dropIfExists($table);
    }

    /**
     * Get the column listing for a given table.
     *
     * @param  string  $table
     */
    public function getColumnListing($table): array
    {
        $database = $this->connection->getConfig('username');
        $table = $this->connection->getTablePrefix().$table;
        /** @var \Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $results = $this->connection->select($grammar->compileColumnExists($database, $table));

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Get the columns for a given table.
     *
     * @param  string  $table
     */
    public function getColumns($table): array
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getPostProcessor()->processColumns(
            $this->connection->selectFromWriteConnection($this->grammar->compileColumns($schema, $table))
        );
    }

    /**
     * Parse the database object reference and extract the schema and table.
     *
     * @param  string  $reference
     * @param  string|bool|null  $withDefaultSchema
     */
    public function parseSchemaAndTable($reference, $withDefaultSchema = null): array
    {
        $parts = explode('.', $reference);

        // We will use the default schema unless the schema has been specified in the
        // query. If the schema has been specified in the query then we can use it
        // instead of a default schema configured in the connection search path.
        $schema = $this->connection->getConfig('username');

        if (count($parts) === 2) {
            $schema = $parts[0];
            array_shift($parts);
        }

        return [$schema, $parts[0]];
    }

    /**
     * Get the indexes for a given table.
     *
     * @param  string  $table
     */
    public function getIndexes($table): array
    {
        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getPostProcessor()->processIndexes(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileIndexes($this->connection->getConfig('username'), $table)
            )
        );
    }

    /**
     * Disable foreign key constraints.
     */
    public function disableForeignKeyConstraints(): bool
    {
        return $this->connection->statement(
            $this->grammar->compileDisableForeignKeyConstraints($this->connection->getConfig('username'))
        );
    }

    /**
     * Enable foreign key constraints.
     */
    public function enableForeignKeyConstraints(): bool
    {
        return $this->connection->statement(
            $this->grammar->compileEnableForeignKeyConstraints($this->connection->getConfig('username'))
        );
    }

    /**
     * Get the tables that belong to the database.
     */
    public function getTables($schema = null): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileTables($schema ?? $this->connection->getConfig('username'))
            )
        );
    }
}
