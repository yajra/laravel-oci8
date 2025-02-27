<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\QueryException;
use Yajra\Oci8\Oci8Connection;

class Sequence
{
    public function __construct(protected Oci8Connection $connection)
    {
    }

    public function create(
        string $name,
        int $start = 1,
        bool $nocache = false,
        int $min = 1,
        int $max = 0,
        int $increment = 1
    ): bool {
        $name = $this->wrapSchema($name);

        $nocache = $nocache ? 'nocache' : '';

        $max = $max ? " maxvalue {$max}" : '';

        $sql = "create sequence {$name} minvalue {$min} {$max} start with {$start} increment by {$increment} {$nocache}";

        return $this->connection->statement($sql);
    }

    public function wrapSchema(string $name): string
    {
        if ($this->connection->getConfig('prefix_schema')) {
            return $this->connection->getConfig('prefix_schema').'.'.$name;
        }

        return $name;
    }

    public function drop(string $name): bool
    {
        if (! $this->exists($name)) {
            return false;
        }

        $name = $this->wrapSchema($name);

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

    public function exists(string $name): bool
    {
        $name = $this->wrapSchema($name);
        $owner = $this->connection->getConfig('username');

        return (bool) $this->connection->scalar(
            "select count(*) from all_sequences where sequence_name=upper('{$name}') and sequence_owner=upper('{$owner}')"
        );
    }

    public function nextValue(string $name): int
    {
        $name = $this->wrapSchema($name);

        return $this->connection->selectOne("SELECT $name.NEXTVAL as \"id\" FROM DUAL")->id;
    }

    public function currentValue(string $name): int
    {
        return $this->lastInsertId($name);
    }

    public function lastInsertId(string $name): int
    {
        if (! $this->exists($name)) {
            return 0;
        }

        try {
            $name = $this->wrapSchema($name);

            return $this->connection->selectOne("select {$name}.currval as \"id\" from dual")->id;
        } catch (QueryException $e) {
            return 0;
        }
    }
}
