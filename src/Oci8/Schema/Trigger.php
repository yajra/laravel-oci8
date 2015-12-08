<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;

class Trigger
{
    /**
     * @var \Illuminate\Database\Connection
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
     * function to create auto increment trigger for a table
     *
     * @param  string $table
     * @param  string $column
     * @param  string $triggerName
     * @param  string $sequenceName
     * @return boolean
     */
    public function autoIncrement($table, $column, $triggerName, $sequenceName)
    {
        if (! $table or ! $column or ! $triggerName or ! $sequenceName) {
            return false;
        }

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
     * function to safely drop trigger db object
     *
     * @param  string $name
     * @return boolean
     */
    public function drop($name)
    {
        if (! $name) {
            return false;
        }

        return $this->connection->statement("
            declare
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
