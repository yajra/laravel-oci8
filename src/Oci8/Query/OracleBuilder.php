<?php

namespace Yajra\Oci8\Query;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

class OracleBuilder extends Builder
{
    /**
     * Insert a new record and get the value of the primary key.
     */
    public function insertLob(array $values, array $binaries, string $sequence = 'id'): int
    {
        /** @var \Yajra\Oci8\Query\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql = $grammar->compileInsertLob($this, $values, $binaries, $sequence);

        $values = $this->cleanBindings($values);
        $binaries = $this->cleanBindings($binaries);

        /** @var \Yajra\Oci8\Query\Processors\OracleProcessor $processor */
        $processor = $this->processor;

        return $processor->saveLob($this, $sql, $values, $binaries);
    }

    /**
     * Update a new record with blob field.
     */
    public function updateLob(array $values, array $binaries, string $sequence = 'id'): bool
    {
        $bindings = array_values(array_merge($values, $this->getBindings()));

        /** @var \Yajra\Oci8\Query\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql = $grammar->compileUpdateLob($this, $values, $binaries, $sequence);

        $values = $this->cleanBindings($bindings);
        $binaries = $this->cleanBindings($binaries);

        /** @var \Yajra\Oci8\Query\Processors\OracleProcessor $processor */
        $processor = $this->processor;

        return $processor->saveLob($this, $sql, $values, $binaries);
    }

    /**
     * Add a "where in" clause to the query.
     * Split one WHERE IN clause into multiple clauses each
     * with up to 1000 expressions to avoid ORA-01795.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false): OracleBuilder
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if (is_array($values) && count($values) > 1000) {
            $chunks = array_chunk($values, 1000);

            return $this->where(function ($query) use ($column, $chunks, $type, $not) {
                foreach ($chunks as $ch) {
                    $sqlClause = $not ? 'where'.$type : 'orWhere'.$type;
                    $query->{$sqlClause}($column, $ch);
                }
            }, null, null, $boolean);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null): static
    {
        if ($this->isQueryable($table)) {
            return $this->fromSub($table, $as);
        }

        $this->from = $as ? "{$table} {$as}" : $table;

        return $this;
    }

    /**
     * Makes "from" fetch from a subquery.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $query
     * @param  string  $as
     */
    public function fromSub($query, $as): static
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->fromRaw('('.$query.') '.$this->grammar->wrapTable($as), $bindings);
    }

    /**
     * Add a subquery join clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $query
     * @param  string  $as
     * @param  \Closure|string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string  $type
     * @param  bool  $where
     */
    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '('.$query.') '.$this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        return $this->join(new Expression($expression), $first, $operator, $second, $type, $where);
    }

    /**
     * Add a subquery cross join to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $query
     * @param  string  $as
     */
    public function crossJoinSub($query, $as): static
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '('.$query.') '.$this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        $this->joins[] = $this->newJoinClause($this, 'cross', new Expression($expression));

        return $this;
    }
}
