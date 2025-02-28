<?php

namespace Yajra\Oci8\Query\Grammars;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Str;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\OracleReservedWords;

/**
 * @property \Yajra\Oci8\Oci8Connection $connection
 */
class OracleGrammar extends Grammar
{
    use OracleReservedWords;

    /**
     * The keyword identifier wrapper format.
     */
    protected string $wrapper = '%s';

    protected string $schemaPrefix = '';

    protected int $maxLength;

    public function __construct(Oci8Connection $connection)
    {
        parent::__construct($connection);

        $this->setSchemaPrefix($connection->getSchemaPrefix());
        $this->setMaxLength($connection->getMaxLength());
    }

    /**
     * @var int
     */
    protected $labelSearchFullText = 1;

    /**
     * Compile a delete statement with joins into SQL.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compileDeleteWithJoins(Builder $query, $table, $where): string
    {
        $alias = last(explode(' as ', $table));

        $joins = $this->compileJoins($query, $query->joins);

        return "delete (select * from {$alias} {$joins} {$where})";
    }

    /**
     * Compile an exists statement into SQL.
     */
    public function compileExists(Builder $query): string
    {
        $q = clone $query;
        $q->columns = [];
        $q->selectRaw('1 as "exists"')
            ->whereRaw('rownum = 1');

        return $this->compileSelect($q);
    }

    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(Builder $query): string
    {
        if (($query->unions || $query->havings) && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does, we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $components = $this->compileComponents($query);
        unset($components['lock']);

        if (isset($query->lock) && isset($query->limit)) {
            unset($components['orders']);
        }

        $sql = trim($this->concatenate($components));

        if ($query->unions) {
            $sql = $this->wrapUnion($sql).' '.$this->compileUnions($query);
        }

        if (isset($query->limit) || isset($query->offset)) {
            $sql = $this->compileAnsiOffset($query, $components);
        }

        if (isset($query->lock)) {
            $sql .= ' '.$this->compileLock($query, $query->lock).' '.$this->compileOrders($query, $query->orders);
        }

        $query->columns = $original;

        return trim($sql);
    }

    /**
     * Create a full ANSI offset clause for the query.
     */
    protected function compileAnsiOffset(Builder $query, array $components): string
    {
        // Improved response time with FIRST_ROWS(n) hint for ORDER BY queries
        if ($query->getConnection()->getConfig('server_version') == '12c') {
            $components['columns'] = str_replace('select', "select /*+ FIRST_ROWS({$query->limit}) */",
                $components['columns']);
            $offset = $query->offset ?: 0;
            $limit = $query->limit;
            $components['limit'] = "offset $offset rows fetch next $limit rows only";

            return $this->concatenate($components);
        }

        $constraint = $this->compileRowConstraint($query);

        $sql = $this->concatenate($components);

        // We are now ready to build the final SQL query so we'll create a common table
        // expression from the query and get the records with row numbers within our
        // given limit and offset value that we just put on as a query constraint.
        return $this->compileTableExpression($sql, $constraint, $query);
    }

    /**
     * Compile the limit / offset row constraint for a query.
     */
    protected function compileRowConstraint(Builder $query): string
    {
        $start = $query->offset + 1;
        $finish = $query->offset + $query->limit;

        if ($query->limit == 1 && is_null($query->offset)) {
            return '= 1';
        }

        if ($query->offset && is_null($query->limit)) {
            return ">= {$start}";
        }

        return "between {$start} and {$finish}";
    }

    /**
     * Compile a common table expression for a query.
     */
    protected function compileTableExpression(string $sql, string $constraint, Builder $query): string
    {
        if ($query->limit == 1 && is_null($query->offset)) {
            return "select * from ({$sql}) where rownum {$constraint}";
        }

        return "select t2.* from ( select rownum AS \"rn\", t1.* from ({$sql}) t1 ) t2 where t2.\"rn\" {$constraint}";
    }

    /**
     * Compile a truncate table statement into SQL.
     */
    public function compileTruncate(Builder $query): array
    {
        return ['truncate table '.$this->wrapTable($query->from) => []];
    }

    /**
     * @param  string  $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_value('.$field.$path.')';
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $table
     */
    public function wrapTable($table, $prefix = null): string
    {
        if ($this->isExpression($table)) {
            return $this->getValue($table);
        }

        $prefix ??= $this->connection->getTablePrefix();

        if (str_contains(strtolower($table), ' as ')) {
            return $this->wrapAliasedTable($table, $prefix);
        }

        $tableName = $this->wrap($prefix.$table);
        $segments = explode(' ', $table);
        if (count($segments) > 1) {
            $tableName = $this->wrap($prefix.$segments[0]).' '.$prefix.$segments[1];
        }

        return $tableName;
    }

    protected function wrapAliasedTable($value, $prefix = null): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        $prefix ??= $this->connection->getTablePrefix();

        return $this->wrapTable($segments[0], $prefix).' '.$this->wrapValue($prefix.$segments[1]);
    }

    /**
     * Return the schema prefix.
     */
    public function getSchemaPrefix(): string
    {
        return $this->connection->getSchemaPrefix();
    }

    /**
     * Get max length.
     */
    public function getMaxLength(): int
    {
        return $this->connection->getMaxLength();
    }

    /**
     * Compile an insert ignore statement into SQL.
     */
    public function compileInsertOrIgnore(Builder $query, array $values): string
    {
        $keys = array_keys(reset($values));
        $columns = $this->columnize($keys);

        $parameters = $this->compileUnionSelectFromDual($values);

        $source = $this->wrapTable('laravel_source');

        $sql = 'merge into '.$this->wrapTable($query->from).' ';
        $sql .= 'using ('.$parameters.') '.$source;

        $uniqueBy = $keys;
        if (strtolower($query->from) == 'cache') {
            $uniqueBy = ['key'];
        }

        $on = collect($uniqueBy)->map(fn ($column) => $this->wrap('laravel_source.'.$column).' = '.$this->wrap($query->from.'.'.$column))->implode(' and ');

        $sql .= ' on ('.$on.') ';

        $columnValues = collect(explode(', ', $columns))->map(fn ($column) => $source.'.'.$column)->implode(', ');

        $sql .= 'when not matched then insert ('.$columns.') values ('.$columnValues.')';

        return $sql;
    }

    /**
     * Set the schema prefix.
     */
    public function setSchemaPrefix(string $prefix): void
    {
        $this->connection->setSchemaPrefix($prefix);
    }

    /**
     * Set max length.
     */
    public function setMaxLength(int $length): void
    {
        $this->connection->setMaxLength($length);
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrapValue($value): string
    {
        if ($value === '*') {
            return $value;
        }

        $value = Str::upper($value);

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  array  $values
     * @param  string  $sequence
     */
    public function compileInsertGetId(Builder $query, $values, $sequence = 'id'): string
    {
        if (empty($sequence)) {
            $sequence = 'id';
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4)[2]['object'];

        if ($backtrace instanceof EloquentBuilder) {
            $model = $backtrace->getModel();
            if ($model->sequence && ! isset($values[$model->getKeyName()]) && $model->incrementing) {
                $values[$sequence] = null;
            }
        }

        return $this->compileInsert($query, $values).' returning '.$this->wrap($sequence).' into ?';
    }

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(Builder $query, array $values): string
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
                $parameter = str_replace(['(', ')'], '', $parameter);
                $insertQueries[] = 'select '.$parameter.' from dual ';
            }
            $parameters = implode('union all ', $insertQueries);

            return "insert into $table ($columns) $parameters";
        }
        $parameters = implode(', ', $value);

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an insert with blob field statement into SQL.
     */
    public function compileInsertLob(Builder $query, array $values, array $binaries, string $sequence = 'id'): string
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

        $columns = $this->columnize(array_keys(reset($values)));
        $binaryColumns = $this->columnize(array_keys(reset($binaries)));
        $columns .= (empty($columns) ? '' : ', ').$binaryColumns;

        $parameters = $this->parameterize(reset($values));
        $binaryParameters = $this->parameterize(reset($binaries));

        $value = array_fill(0, count($values), "$parameters");
        $binaryValue = array_fill(0, count($binaries), str_replace('?', 'EMPTY_BLOB()', $binaryParameters));

        $value = array_merge($value, $binaryValue);
        $parameters = implode(', ', array_filter($value));

        return "insert into $table ($columns) values ($parameters) returning ".$binaryColumns.', '.$this->wrap($sequence).' into '.$binaryParameters.', ?';
    }

    /**
     * Compile an update statement into SQL.
     */
    public function compileUpdateLob(Builder $query, array $values, array $binaries, string $sequence = 'id'): string
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key).' = '.$this->parameter($value);
        }

        $columns = implode(', ', $columns);

        // set blob variables
        if (! is_array(reset($binaries))) {
            $binaries = [$binaries];
        }
        $binaryColumns = $this->columnize(array_keys(reset($binaries)));
        $binaryParameters = $this->parameterize(reset($binaries));

        // create EMPTY_BLOB sql for each binary
        $binarySql = [];
        foreach (explode(',', $binaryColumns) as $binary) {
            $binarySql[] = "$binary = EMPTY_BLOB()";
        }

        // prepare binary SQLs
        if (count($binarySql)) {
            $binarySql = (empty($columns) ? '' : ', ').implode(',', $binarySql);
        }

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';
        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $where = $this->compileWheres($query);

        return "update {$table}{$joins} set $columns$binarySql $where returning ".$binaryColumns.', '.$this->wrap($sequence).' into '.$binaryParameters.', ?';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compileLock(Builder $query, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return 'for update';
        }

        return '';
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  int  $limit
     */
    protected function compileLimit(Builder $query, $limit): string
    {
        return '';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  int  $offset
     */
    protected function compileOffset(Builder $query, $offset): string
    {
        return '';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     */
    protected function whereDate(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return "trunc({$this->wrap($where['column'])}) {$where['operator']} $value";
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     */
    protected function dateBasedWhere($type, Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return "extract ($type from {$this->wrap($where['column'])}) {$where['operator']} $value";
    }

    /**
     * Compile a "where not in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param  array  $where
     */
    protected function whereNotInRaw(Builder $query, $where): string
    {
        if (! empty($where['values'])) {
            if (is_array($where['values']) && count($where['values']) > 1000) {
                return $this->resolveClause($where['column'], $where['values'], 'not in');
            } else {
                return $this->wrap($where['column']).' not in ('.implode(', ', $where['values']).')';
            }
        }

        return '1 = 1';
    }

    /**
     * Compile a "where in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param  array  $where
     */
    protected function whereInRaw(Builder $query, $where): string
    {
        if (! empty($where['values'])) {
            if (is_array($where['values']) && count($where['values']) > 1000) {
                return $this->resolveClause($where['column'], $where['values'], 'in');
            } else {
                return $this->wrap($where['column']).' in ('.implode(', ', $where['values']).')';
            }
        }

        return '0 = 1';
    }

    /**
     * Compile a "where fulltext" clause.
     *
     * @param  array  $where
     * @return string
     */
    public function whereFullText(Builder $query, $where)
    {
        // Build the fullText clause
        $fullTextClause = collect($where['columns'])
            ->map(function ($column, $index) use ($where) {
                $labelSearchFullText = $index > 0 ? ++$this->labelSearchFullText : $this->labelSearchFullText;

                return "CONTAINS({$this->wrap($column)}, {$this->parameter($where['value'])}, {$labelSearchFullText}) > 0";
            })
            ->implode(" {$where['boolean']} ");

        // Count the total number of columns in the clauses
        $fullTextClauseCount = array_reduce($query->wheres, fn ($count, $queryWhere) => $queryWhere['type'] === 'Fulltext' ? $count + count($queryWhere['columns']) : $count, 0);

        // Reset the counter if all columns were used in the clause
        if ($fullTextClauseCount === $this->labelSearchFullText) {
            $this->labelSearchFullText = 0;
        }

        // Increment the counter for the next clause
        $this->labelSearchFullText++;

        return $fullTextClause;
    }

    private function resolveClause($column, $values, $type): string
    {
        $chunks = array_chunk($values, 1000);
        $whereClause = '';
        $i = 0;
        $type = $this->wrap($column).' '.$type.' ';
        foreach ($chunks as $ch) {
            // Add or only at the second loop
            if ($i === 1) {
                $type = ' or '.$type.' ';
            }
            $whereClause .= $type.'('.implode(', ', $ch).')';
            $i++;
        }

        return '('.$whereClause.')';
    }

    /**
     * Compile a union aggregate query into SQL.
     */
    protected function compileUnionAggregate(Builder $query): string
    {
        $sql = $this->compileAggregate($query, $query->aggregate);

        $query->aggregate = null;

        return $sql.' from ('.$this->compileSelect($query).') '.$this->wrapTable('temp_table');
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     */
    public function compileRandom($seed): string
    {
        return 'DBMS_RANDOM.RANDOM';
    }

    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $columns = $this->columnize(array_keys(reset($values)));
        $parameters = $this->compileUnionSelectFromDual($values);

        $source = $this->wrapTable('laravel_source');

        $sql = 'merge into '.$this->wrapTable($query->from).' ';
        $sql .= 'using ('.$parameters.') '.$source;

        $on = collect($uniqueBy)->map(fn ($column) => $this->wrap('laravel_source.'.$column).' = '.$this->wrap($query->from.'.'.$column))->implode(' and ');

        $sql .= ' on ('.$on.') ';

        if ($update) {
            $update = collect($update)
                ->reject(fn ($value, $key) => in_array($value, $uniqueBy))
                ->map(fn ($value, $key) => is_numeric($key)
                    ? $this->wrap($value).' = '.$this->wrap('laravel_source.'.$value)
                    : $this->wrap($key).' = '.$this->parameter($value))
                ->implode(', ');

            $sql .= 'when matched then update set '.$update.' ';
        }

        $columnValues = collect(explode(', ', $columns))->map(fn ($column) => $source.'.'.$column)->implode(', ');

        $sql .= 'when not matched then insert ('.$columns.') values ('.$columnValues.')';

        return $sql;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param  string  $name
     */
    public function compileSavepointRollBack($name): string
    {
        return 'ROLLBACK TO '.$name;
    }

    protected function compileUnionSelectFromDual(array $values): string
    {
        return collect($values)->map(function ($record) {
            $values = collect($record)->map(fn ($value, $key) => '? as '.$this->wrap($key))->implode(', ');

            return 'select '.$values.' from dual';
        })->implode(' union all ');
    }

    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     */
    protected function whereLike(Builder $query, $where): string
    {
        $where['operator'] = $where['not'] ? 'not like' : 'like';

        if ($where['caseSensitive']) {
            return $this->whereBasic($query, $where);
        }

        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return 'upper('.$this->wrap($where['column']).') '.$operator.' upper('.$value.')';
    }
}
