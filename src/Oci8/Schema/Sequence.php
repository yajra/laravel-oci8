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
        $nocache = $nocache ? 'nocache' : '';

        $max = $max ? " maxvalue {$max}" : '';

        $sql = "create sequence {$name} minvalue {$min} {$max} start with {$start} increment by {$increment} {$nocache}";

        return $this->connection->statement(trim($sql));
    }

    public function drop(string $name): bool
    {
        if (! $this->exists($name)) {
            return true;
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

    public function exists(string $name): bool
    {
        $owner = $this->connection->getSchema();

        if (str_contains($name, '.')) {
            [$owner, $name] = explode('.', $name);
        }

        $name = $this->unquote($name);
        $owner = $this->unquote($owner);
        $sql = "select count(*) from all_sequences where sequence_name=upper('{$name}') and sequence_owner=upper('{$owner}')";

        return (bool) $this->connection->scalar($sql);
    }

    public function nextValue(string $name): int
    {
        $name = $this->wrap($name);

        return $this->connection->selectOne("SELECT $name.NEXTVAL as \"id\" FROM DUAL")->id;
    }

    public function currentValue(string $name): int
    {
        return $this->lastInsertId($name);
    }

    public function lastInsertId(string $name): int
    {
        $name = $this->wrap($name);

        if (! $this->exists($name)) {
            return 0;
        }

        try {
            return $this->connection->selectOne("select {$name}.currval as \"id\" from dual")->id;
        } catch (QueryException) {
            return 0;
        }
    }

    public function forceCreate(string $name): bool
    {
        $name = $this->wrap($name);

        $this->drop($name);

        return $this->create($name);
    }

    public function wrap(string $name): string
    {
        $owner = '';
        $name = $this->unquote($name);

        if (str_contains($name, '.')) {
            [$owner, $name] = explode('.', $name);
            $owner = $this->unquote($owner).'.';
        }

        return $this->connection->getQueryGrammar()->wrapTable($owner.$name);
    }

    public function unquote(string $name): string
    {
        return str_replace('"', '', $name);
    }
}
