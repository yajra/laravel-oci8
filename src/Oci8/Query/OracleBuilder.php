<?php

namespace Yajra\Oci8\Query;

use Illuminate\Database\Query\Builder;

class OracleBuilder extends Builder
{
    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array $values
     * @param  array $binaries
     * @param  string $sequence
     * @return int
     */
    public function insertLob(array $values, array $binaries, $sequence = 'id')
    {
        /** @var \Yajra\Oci8\Query\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql     = $grammar->compileInsertLob($this, $values, $binaries, $sequence);

        $values   = $this->cleanBindings($values);
        $binaries = $this->cleanBindings($binaries);

        /** @var \Yajra\Oci8\Query\Processors\OracleProcessor $processor */
        $processor = $this->processor;

        return $processor->saveLob($this, $sql, $values, $binaries);
    }

    /**
     * Update a new record with blob field.
     *
     * @param  array $values
     * @param  array $binaries
     * @param  string $sequence
     * @return boolean
     */
    public function updateLob(array $values, array $binaries, $sequence = 'id')
    {
        $bindings = array_values(array_merge($values, $this->getBindings()));

        /** @var \Yajra\Oci8\Query\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql     = $grammar->compileUpdateLob($this, $values, $binaries, $sequence);

        $values   = $this->cleanBindings($bindings);
        $binaries = $this->cleanBindings($binaries);

        /** @var \Yajra\Oci8\Query\Processors\OracleProcessor $processor */
        $processor = $this->processor;

        return $processor->saveLob($this, $sql, $values, $binaries);
    }

    /**
     * Add a "where in" clause to the query.
     * Split one WHERE IN clause into multiple clauses each
     * with up to 1000 expressions to avoid ORA-01795
     *
     * @param  string $column
     * @param  mixed $values
     * @param  string $boolean
     * @param  bool $not
     * @return \Illuminate\Database\Query\Builder|\Yajra\Oci8\Query\OracleBuilder
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if (count($values) > 1000) {
            $chunks = array_chunk($values, 1000);

            return $this->where(function ($query) use ($column, $chunks, $type) {
                $firstIteration = true;
                foreach ($chunks as $ch) {
                    $sqlClause = $firstIteration ? 'where' . $type : 'orWhere' . $type;
                    $query->$sqlClause($column, $ch);
                    $firstIteration = false;
                }
            }, null, null, $boolean);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        if ($this->lock) {
            $this->connection->beginTransaction();
            $result = $this->connection->select($this->toSql(), $this->getBindings(), ! $this->useWritePdo);
            $this->connection->commit();

            return $result;
        }

        return $this->connection->select($this->toSql(), $this->getBindings(), ! $this->useWritePdo);
    }
}
