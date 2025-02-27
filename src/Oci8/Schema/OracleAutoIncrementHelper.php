<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class OracleAutoIncrementHelper
{
    /**
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * @var \Yajra\Oci8\Schema\Trigger
     */
    protected $trigger;

    /**
     * @var \Yajra\Oci8\Schema\Sequence
     */
    protected $sequence;

    /**
     * @param  \Illuminate\Database\Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->sequence = new Sequence($connection);
        $this->trigger = new Trigger($connection);
    }

    /**
     * create sequence and trigger for autoIncrement support.
     *
     * @param  Blueprint  $blueprint
     * @param  string  $table
     * @return null
     */
    public function createAutoIncrementObjects(Blueprint $blueprint, $table)
    {
        $column = $this->getQualifiedAutoIncrementColumn($blueprint);

        // return if no qualified AI column
        if (is_null($column)) {
            return;
        }

        $col = $column->name;
        $start = $column->start ?? $column->startingValue ?? 1;

        // get table prefix
        $prefix = $this->connection->getTablePrefix();

        // create sequence for auto increment
        $sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
        $this->sequence->create($sequenceName, $start, $column->nocache);

        // create trigger for auto increment work around
        $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
        $this->trigger->autoIncrement($prefix.$table, $col, $triggerName, $sequenceName);
    }

    /**
     * Get qualified autoincrement column.
     *
     * @param  Blueprint  $blueprint
     * @return \Illuminate\Support\Fluent|null
     */
    public function getQualifiedAutoIncrementColumn(Blueprint $blueprint)
    {
        $columns = $blueprint->getColumns();

        // search for primary key / autoIncrement column
        foreach ($columns as $column) {
            // if column is autoIncrement set the primary col name
            if ($column->autoIncrement) {
                return $column;
            }
        }
    }

    /**
     * Create an object name that limits to 30 or 128 chars depending on the server version.
     *
     * @param  string  $prefix
     * @param  string  $table
     * @param  string  $col
     * @param  string  $type
     * @return string
     */
    private function createObjectName(string $prefix, string $table, string $col, string $type): string
    {
        $maxLength = $this->connection->getMaxLength();

        return mb_substr($prefix.$table.'_'.$col.'_'.$type, 0, $maxLength);
    }

    /**
     * Drop sequence and triggers if exists, autoincrement objects.
     *
     * @param  string  $table
     */
    public function dropAutoIncrementObjects(string $table): void
    {
        // drop sequence and trigger object
        $prefix = $this->connection->getTablePrefix();

        // get the actual primary column name from table
        $col = $this->getPrimaryKey($prefix.$table);

        // if primary key col is set, drop auto increment objects
        if (! empty($col)) {
            // drop sequence for auto increment
            $sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
            $this->sequence->drop($sequenceName);

            // drop trigger for auto increment work around
            $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
            $this->trigger->drop($triggerName);
        }
    }

    /**
     * Get the table's primary key column name.
     */
    public function getPrimaryKey(string $table): string
    {
        $owner = $this->connection->getConfig('username');

        if (str_contains($table, '.'))  {
            [$owner, $table] = explode('.', $table);
        }

        $sql = "SELECT cols.column_name
            FROM all_constraints cons, all_cons_columns cols
            WHERE upper(cols.table_name) = upper('{$table}')
                AND upper(cons.owner) = upper('{$owner}')
                AND cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND cols.position = 1
            ORDER BY cols.table_name, cols.position";

        $data = $this->connection->selectOne($sql);

        if ($data) {
            return $data->column_name;
        }

        return '';
    }

    /**
     * Get sequence instance.
     */
    public function getSequence(): Sequence
    {
        return $this->sequence;
    }

    /**
     * Set the sequence instance.
     */
    public function setSequence(Sequence $sequence): void
    {
        $this->sequence = $sequence;
    }

    /**
     * Get the trigger instance.
     */
    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }

    /**
     * Set the trigger instance.
     */
    public function setTrigger(Trigger $trigger): void
    {
        $this->trigger = $trigger;
    }
}
