<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\QueryException;
use Yajra\Oci8\Oci8Connection;

class Sequence
{
    public function __construct(protected Oci8Connection $connection) {}

    public function create(
        string $name,
        int $start = 1,
        bool $nocache = false,
        int $min = 1,
        int $max = 0,
        int $increment = 1
    ): bool {
        $name = $this->withSchemaPrefix(
            $this->connection->getQueryGrammar()->wrap($name)
        );

        $nocache = $nocache ? 'nocache' : '';

        $max = $max ? " maxvalue {$max}" : '';

        $sql = "create sequence {$name} minvalue {$min} {$max} start with {$start} increment by {$increment} {$nocache}";

        return $this->connection->statement(trim($sql));
    }

    public function withSchemaPrefix(string $name): string
    {
        return $this->connection->withSchemaPrefix($name);
    }

    public function drop(string $name): bool
    {
        if (! $this->exists($name)) {
            return true;
        }

        $name = $this->withSchemaPrefix($name);

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
        $owner = $this->connection->getSchema();

        if (str_contains($name, '.')) {
            [$owner, $name] = explode('.', $name);
        }

        return (bool) $this->connection->scalar(
            "select count(*) from all_sequences where sequence_name=upper('{$name}') and sequence_owner=upper('{$owner}')"
        );
    }

    public function nextValue(string $name): int
    {
        $name = $this->withSchemaPrefix($name);

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
            $name = $this->withSchemaPrefix($name);

            return $this->connection->selectOne("select {$name}.currval as \"id\" from dual")->id;
        } catch (QueryException) {
            return 0;
        }
    }

    public function forceCreate(string $name): bool
    {
        $this->drop($name);

        return $this->create($name);
    }
}
