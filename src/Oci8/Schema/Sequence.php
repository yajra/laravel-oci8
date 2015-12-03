<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;

class Sequence
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
     * function to create oracle sequence
     *
     * @param  string $name
     * @param  integer $start
     * @param  boolean $nocache
     * @return boolean
     */
    public function create($name, $start = 1, $nocache = false)
    {
        if (! $name) {
            return false;
        }

        $nocache = $nocache ? 'nocache' : '';

        return $this->connection->statement("create sequence {$name} start with {$start} {$nocache}");
    }

    /**
     * function to safely drop sequence db object
     *
     * @param  string $name
     * @return boolean
     */
    public function drop($name)
    {
        // check if a valid name and sequence exists
        if (! $name or ! $this->exists($name)) {
            return false;
        }

        return $this->connection->statement("
            declare
                e exception;
                pragma exception_init(e,-02289);
            begin
                execute immediate 'drop sequence {$name}';
            exception
            when e then
                null;
            end;");
    }

    /**
     * function to check if sequence exists
     *
     * @param  string $name
     * @return boolean
     */
    public function exists($name)
    {
        if (! $name) {
            return false;
        }

        return $this->connection->selectOne("select *
            from all_sequences
            where
                sequence_name=upper('{$name}')
                and sequence_owner=upper(user)
            ");
    }

    /**
     * get sequence next value
     *
     * @param  string $name
     * @return integer
     */
    public function nextValue($name)
    {
        if (! $name) {
            return 0;
        }

        return $this->connection->selectOne("SELECT $name.NEXTVAL as id FROM DUAL")->id;
    }

    /**
     * same function as lastInsertId. added for clarity with oracle sql statement.
     *
     * @param  string $name
     * @return integer
     */
    public function currentValue($name)
    {
        return $this->lastInsertId($name);
    }

    /**
     * function to get oracle sequence last inserted id
     *
     * @param  string $name
     * @return integer
     */
    public function lastInsertId($name)
    {
        // check if a valid name and sequence exists
        if (! $name or ! $this->exists($name)) {
            return 0;
        }

        return $this->connection->selectOne("select {$name}.currval as id from dual")->id;
    }
}
