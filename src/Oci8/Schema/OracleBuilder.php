<?php

namespace Yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

class OracleBuilder extends Builder
{
    /**
     * @var \Yajra\Oci8\Schema\OracleAutoIncrementHelper
     */
    public $helper;

    /**
     * @var \Yajra\Oci8\Schema\Comment
     */
    public $comment;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
        $this->helper  = new OracleAutoIncrementHelper($connection);
        $this->comment = new Comment($connection);
    }

    /**
     * Create a new table on the schema.
     *
     * @param  string $table
     * @param  Closure $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function create($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        $this->build($blueprint);

        $this->comment->setComments($blueprint);

        $this->helper->createAutoIncrementObjects($blueprint, $table);
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param  string $table
     * @param  Closure $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        $blueprint = new OracleBlueprint($table, $callback);
        $blueprint->setTablePrefix($this->connection->getTablePrefix());

        return $blueprint;
    }

    /**
     * Changes an existing table on the schema.
     *
     * @param  string $table
     * @param  Closure $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function table($table, Closure $callback)
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
     * @param  string $table
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function drop($table)
    {
        $this->helper->dropAutoIncrementObjects($table);
        parent::drop($table);
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @param string $table
     * @return \Illuminate\Support\Fluent
     */
    public function dropIfExists($table)
    {
        $this->helper->dropAutoIncrementObjects($table);
        parent::dropIfExists($table);
    }

    /**
     * Determine if the given table exists.
     *
     * @param  string $table
     * @return bool
     */
    public function hasTable($table)
    {
        /** @var \Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql     = $grammar->compileTableExists();

        $database = $this->connection->getConfig('username');
        $table    = $this->connection->getTablePrefix() . $table;

        return count($this->connection->select($sql, [$database, $table])) > 0;
    }

    /**
     * Get the column listing for a given table.
     *
     * @param  string $table
     * @return array
     */
    public function getColumnListing($table)
    {
        $database = $this->connection->getConfig('username');
        $table    = $this->connection->getTablePrefix() . $table;
        /** @var \Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $results = $this->connection->select($grammar->compileColumnExists($database, $table));

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }
}
