<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Yajra\Oci8\OracleReservedWords;

class Trigger
{
    use OracleReservedWords;

    /**
     * @var \Illuminate\Database\Connection|\Yajra\Oci8\Oci8Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Function to create auto increment trigger for a table.
     *
     * @param  string $table
     * @param  string $column
     * @param  string $triggerName
     * @param  string $sequenceName
     * @return boolean
     */
    public function autoIncrement($table, $column, $triggerName, $sequenceName)
    {
        if (! $table || ! $column || ! $triggerName || ! $sequenceName) {
            return false;
        }

        $table  = $this->wrapValue($table);
        $column = $this->wrapValue($column);

        return $this->connection->statement("
            create trigger $triggerName
            before insert on {$table}
            for each row
                begin
            if :new.{$column} is null then
                select {$sequenceName}.nextval into :new.{$column} from dual;
            end if;
            end;");
    }

    /**
     * Wrap value if reserved word.
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return $this->isReserved($value) ? '"' . $value . '"' : $value;
    }

    /**
     * Function to safely drop trigger db object.
     *
     * @param  string $name
     * @return boolean
     */
    public function drop($name)
    {
        if (! $name) {
            return false;
        }

        return $this->connection->statement("declare
                e exception;
                pragma exception_init(e,-4080);
            begin
                execute immediate 'drop trigger {$name}';
            exception
            when e then
                null;
            end;");
    }
}
