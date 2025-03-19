<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Yajra\Oci8\Oci8Connection;

class OracleAutoIncrementHelper
{
    protected Trigger $trigger;

    protected Sequence $sequence;

    public function __construct(protected Oci8Connection $connection)
    {
        $this->sequence = new Sequence($connection);
        $this->trigger = new Trigger($connection);
    }

    /**
     * Create a sequence and trigger for autoIncrement support.
     */
    public function createAutoIncrementObjects(Blueprint $blueprint, string $table): void
    {
        $column = $this->getQualifiedAutoIncrementColumn($blueprint);

        // return if no qualified AI column
        if (is_null($column)) {
            return;
        }

        $col = $column->name;
        $start = $column->start ?? $column->startingValue ?? 1;

        // create sequence for auto increment
        $sequenceName = $this->createObjectName($table, $col, 'seq');
        $this->sequence->create($sequenceName, $start, (bool) $column->nocache);

        // create trigger for auto increment work around
        $triggerName = $this->createObjectName($table, $col, 'trg');
        $this->trigger->autoIncrement($table, $col, $triggerName, $sequenceName);
    }

    /**
     * Get qualified autoincrement column.
     */
    public function getQualifiedAutoIncrementColumn(Blueprint $blueprint): ?Fluent
    {
        $columns = $blueprint->getColumns();

        // search for primary key / autoIncrement column
        foreach ($columns as $column) {
            // if column is autoIncrement set the primary col name
            if ($column->autoIncrement) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Create an object name that limits to 30 or 128 chars depending on the server version.
     */
    private function createObjectName(string $table, string $col, string $type): string
    {
        $maxLength = $this->connection->getMaxLength();
        $table = $this->connection->getTablePrefix().$table;

        return mb_substr($table.'_'.$col.'_'.$type, 0, $maxLength);
    }

    /**
     * Drop the sequence and triggers if exists, autoincrement objects.
     */
    public function dropAutoIncrementObjects(string $table): void
    {
        $col = $this->getPrimaryKey($table);

        // if primary key col is set, drop auto increment objects
        if (! empty($col)) {
            // drop sequence for auto increment
            $sequenceName = $this->createObjectName($table, $col, 'seq');
            $this->sequence->drop($sequenceName);

            // drop trigger for auto increment work around
            $triggerName = $this->createObjectName($table, $col, 'trg');
            $this->trigger->drop($triggerName);
        }
    }

    /**
     * Get the table's primary key column name.
     */
    public function getPrimaryKey(string $table): string
    {
        $table = $this->connection->getQueryGrammar()->wrapTable($table);
        $owner = $this->connection->getSchema();

        if (str_contains($table, '.')) {
            [$owner, $table] = explode('.', $table);
        }

        $table = str_replace('"', '', $table);
        $owner = str_replace('"', '', $owner);

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
}
