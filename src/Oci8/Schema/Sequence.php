<?php

namespace Yajra\Oci8\Schema;

use Yajra\Oci8\Oci8Connection;

class Sequence
{
    /**
     * @var \Yajra\Oci8\Oci8Connection
     */
    protected Oci8Connection $connection;

    /**
     * @param  \Yajra\Oci8\Oci8Connection  $connection
     */
    public function __construct(Oci8Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * function to create oracle sequence.
     *
     * @param  string  $name
     * @param  int  $start
     * @param  bool  $nocache
     * @param  int  $min
     * @param  bool  $max
     * @param  int  $increment
     * @return bool
     */
    public function create(
        string $name,
        int $start = 1,
        bool $nocache = false,
        int $min = 1,
        bool $max = false,
        int $increment = 1
    ): bool {
        $name = $this->wrap($name);

        $nocache = $nocache ? 'nocache' : '';

        $max = $max ? " maxvalue {$max}" : '';

        $sequence_stmt = "create sequence {$name} minvalue {$min} {$max} start with {$start} increment by {$increment} {$nocache}";

        return $this->connection->statement($sequence_stmt);
    }

    /**
     * Wrap sequence name with schema prefix.
     *
     * @param  string  $name
     * @return string
     */
    public function wrap(string $name): string
    {
        if ($this->connection->getConfig('prefix_schema')) {
            return $this->connection->getConfig('prefix_schema').'.'.$name;
        }

        return $name;
    }

    /**
     * function to safely drop sequence db object.
     *
     * @param  string  $name
     * @return bool
     */
    public function drop(string $name): bool
    {
        // check if a valid name and sequence exists
        if (! $name || ! $this->exists($name)) {
            return false;
        }

        $name = $this->wrap($name);

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
     * function to check if sequence exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        $name = $this->wrap($name);

        /** @var \stdClass $sequence */
        $sequence = $this->connection->selectOne(
            "select * from all_sequences where sequence_name=upper('{$name}') and sequence_owner=upper(user)"
        );

        return is_object($sequence);
    }

    /**
     * get sequence next value.
     *
     * @param  string|null  $name
     * @return int
     */
    public function nextValue(string $name = null): int
    {
        if (! $name) {
            return 0;
        }

        $name = $this->wrap($name);

        /** @var \stdClass $sequence */
        $sequence = $this->connection->selectOne("SELECT $name.NEXTVAL as \"id\" FROM DUAL");

        return $sequence->id;
    }

    /**
     * same function as lastInsertId. added for clarity with oracle sql statement.
     *
     * @param  string  $name
     * @return int
     */
    public function currentValue(string $name): int
    {
        return $this->lastInsertId($name);
    }

    /**
     * function to get oracle sequence last inserted id.
     *
     * @param  string  $name
     * @return int
     */
    public function lastInsertId(string $name): int
    {
        // check if a valid name and sequence exists
        if (! $name || ! $this->exists($name)) {
            return 0;
        }

        $name = $this->wrap($name);

        /** @var \stdClass $sequence */
        $sequence = $this->connection->selectOne("select $name.currval as \"id\" from dual");

        return $sequence->id;
    }
}
