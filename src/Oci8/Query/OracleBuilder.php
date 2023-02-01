<?php

namespace Yajra\Oci8\Query;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

class OracleBuilder extends Builder
{
    /**
     * Run a pagination count query.
     *
     * @param  array  $columns
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();

            if (is_null($clone->columns) && ! empty($this->joins)) {
                $clone->select($this->from.'.*');
            }

            return $this->newQuery()
                ->from(new Expression('('.$clone->toSql().')'))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get()->all();
        }

        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        $results = $this->runPaginationCountQuery($columns);

        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (! isset($results[0])) {
            return 0;
        } elseif (is_object($results[0])) {
            return (int) (property_exists($results[0], 'AGGREGATE') ? $results[0]->AGGREGATE : $results[0]->aggregate);   // to solve the Oracle issue: auto-convert field to uppercase
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  array  $binaries
     * @param  string  $sequence
     * @return int
     */
    public function insertLob(array $values, array $binaries, $sequence = 'id')
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
     *
     * @param  array  $values
     * @param  array  $binaries
     * @param  string  $sequence
     * @return bool
     */
    public function updateLob(array $values, array $binaries, $sequence = 'id')
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
     * @return \Illuminate\Database\Query\Builder|\Yajra\Oci8\Query\OracleBuilder
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
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

    /**
     * Set the table which the query is targeting.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null)
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
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function fromSub($query, $as)
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
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
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
     * @return $this
     */
    public function crossJoinSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '('.$query.') '.$this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        $this->joins[] = $this->newJoinClause($this, 'cross', new Expression($expression));

        return $this;
    }

    /**
     * Clone the query.
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }
}
