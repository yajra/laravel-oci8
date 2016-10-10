<?php

namespace Yajra\Oci8\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Yajra\Oci8\OracleReservedWords;

class OracleGrammar extends Grammar
{
    use OracleReservedWords;

    /**
     * The keyword identifier wrapper format.
     *
     * @var string
     */
    protected $wrapper = '%s';

    /**
     * @var string
     */
    protected $schema_prefix = '';

    /**
     * Compile an exists statement into SQL.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $q          = clone $query;
        $q->columns = [];
        $q->selectRaw('1 as "exists"')
          ->whereRaw("rownum = 1");

        return $this->compileSelect($q);
    }

    /**
     * Compile a select query into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $components = $this->compileComponents($query);

        // If an offset is present on the query, we will need to wrap the query in
        // a big "ANSI" offset syntax block. This is very nasty compared to the
        // other database systems but is necessary for implementing features.
        if ($this->isPaginationable($query, $components)) {
            return $this->compileAnsiOffset($query, $components);
        }

        return trim($this->concatenate($components));
    }

    /**
     * @param Builder $query
     * @param array $components
     * @return bool
     */
    protected function isPaginationable(Builder $query, array $components)
    {
        return ($query->limit > 0 || $query->offset > 0) && ! array_key_exists('lock', $components);
    }

    /**
     * Create a full ANSI offset clause for the query.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $components
     * @return string
     */
    protected function compileAnsiOffset(Builder $query, $components)
    {
        $constraint = $this->compileRowConstraint($query);

        $sql = $this->concatenate($components);

        // We are now ready to build the final SQL query so we'll create a common table
        // expression from the query and get the records with row numbers within our
        // given limit and offset value that we just put on as a query constraint.
        $temp = $this->compileTableExpression($sql, $constraint, $query);

        return $temp;
    }

    /**
     * Compile the limit / offset row constraint for a query.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @return string
     */
    protected function compileRowConstraint($query)
    {
        $start = $query->offset + 1;

        if ($query->limit == 1) {
            return "= 1";
        }

        if ($query->limit > 1) {
            $finish = $query->offset + $query->limit;

            return "between {$start} and {$finish}";
        }

        return ">= {$start}";
    }

    /**
     * Compile a common table expression for a query.
     *
     * @param  string $sql
     * @param  string $constraint
     * @param Builder $query
     * @return string
     */
    protected function compileTableExpression($sql, $constraint, $query)
    {
        if ($query->limit > 1) {
            return "select t2.* from ( select rownum AS \"rn\", t1.* from ({$sql}) t1 ) t2 where t2.\"rn\" {$constraint}";
        } else {
            return "select * from ({$sql}) where rownum {$constraint}";
        }
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate table ' . $this->wrapTable($query->from) => []];
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ($this->isExpression($table)) {
            return $this->getValue($table);
        }

        if (strpos(strtolower($table), ' as ') !== false) {
            $table = str_replace(' as ', ' ', $table);
        }

        return $this->getSchemaPrefix() . $this->wrap($this->tablePrefix . $table, true);
    }

    /**
     * Return the schema prefix
     *
     * @return string
     */
    public function getSchemaPrefix()
    {
        return ! empty($this->schema_prefix) ? $this->schema_prefix . '.' : '';
    }

    /**
     * Set the shema prefix
     *
     * @param string $prefix
     */
    public function setSchemaPrefix($prefix)
    {
        $this->schema_prefix = $prefix;
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $values
     * @param  string $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence = 'id')
    {
        if (empty($sequence)) {
            $sequence = 'id';
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4)[3]['object'];

        if (method_exists($backtrace, 'getModel')) {
            $model = $backtrace->getModel();
            if ($model->sequence && ! isset($values[$model->getKeyName()]) && $model->incrementing) {
                $values[$sequence] = null;
            }
        }

        return $this->compileInsert($query, $values) . ' returning ' . $this->wrap($sequence) . ' into ?';
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same amount of parameter
        // bindings so we can just go off the first list of values in this array.
        $parameters = $this->parameterize(reset($values));

        $value = array_fill(0, count($values), "($parameters)");

        if (count($value) > 1) {
            $insertQueries = [];
            foreach ($value as $parameter) {
                $parameter       = (str_replace(['(', ')'], '', $parameter));
                $insertQueries[] = "select " . $parameter . " from dual ";
            }
            $parameters = implode('union all ', $insertQueries);

            return "insert into $table ($columns) $parameters";
        }
        $parameters = implode(', ', $value);

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an insert with blob field statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $values
     * @param  array $binaries
     * @param  string $sequence
     * @return string
     */
    public function compileInsertLob(Builder $query, $values, $binaries, $sequence = 'id')
    {
        if (empty($sequence)) {
            $sequence = 'id';
        }

        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        if (! is_array(reset($binaries))) {
            $binaries = [$binaries];
        }

        $columns       = $this->columnize(array_keys(reset($values)));
        $binaryColumns = $this->columnize(array_keys(reset($binaries)));
        $columns .= (empty($columns) ? '' : ', ') . $binaryColumns;

        $parameters       = $this->parameterize(reset($values));
        $binaryParameters = $this->parameterize(reset($binaries));

        $value       = array_fill(0, count($values), "$parameters");
        $binaryValue = array_fill(0, count($binaries), str_replace('?', 'EMPTY_BLOB()', $binaryParameters));

        $value      = array_merge($value, $binaryValue);
        $parameters = implode(', ', array_filter($value));

        return "insert into $table ($columns) values ($parameters) returning " . $binaryColumns . ', ' . $this->wrap($sequence) . ' into ' . $binaryParameters . ', ?';
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $values
     * @param  array $binaries
     * @param  string $sequence
     * @return string
     */
    public function compileUpdateLob(Builder $query, array $values, array $binaries, $sequence = 'id')
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ' . $this->parameter($value);
        }

        $columns = implode(', ', $columns);

        // set blob variables
        if (! is_array(reset($binaries))) {
            $binaries = [$binaries];
        }
        $binaryColumns    = $this->columnize(array_keys(reset($binaries)));
        $binaryParameters = $this->parameterize(reset($binaries));

        // create EMPTY_BLOB sql for each binary
        $binarySql = [];
        foreach ((array) $binaryColumns as $binary) {
            $binarySql[] = "$binary = EMPTY_BLOB()";
        }

        // prepare binary SQLs
        if (count($binarySql)) {
            $binarySql = (empty($columns) ? '' : ', ') . implode(',', $binarySql);
        }

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        if (isset($query->joins)) {
            $joins = ' ' . $this->compileJoins($query, $query->joins);
        } else {
            $joins = '';
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $where = $this->compileWheres($query);

        return "update {$table}{$joins} set $columns$binarySql $where returning " . $binaryColumns . ', ' . $this->wrap($sequence) . ' into ' . $binaryParameters . ', ?';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  bool|string $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value) {
            return 'for update';
        }

        return '';
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  int $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return '';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  int $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return '';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return "trunc({$this->wrap($where['column'])}) {$where['operator']} $value";
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string $type
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return "extract ($type from {$this->wrap($where['column'])}) {$where['operator']} $value";
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($this->isReserved($value)) {
            return parent::wrapValue($value);
        }

        return $value !== '*' ? sprintf($this->wrapper, $value) : $value;
    }
}
