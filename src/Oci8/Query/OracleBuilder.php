<?php

namespace Yajra\Oci8\Query;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\Processors\OracleProcessor;

class OracleBuilder extends Builder
{
    /**
     * Create a bindable Oracle vector value for insert and update queries.
     *
     * @param  Arrayable<int, float|int>|array<int, float|int>  $vector
     *
     * @throws JsonException
     */
    public function vectorValue(Arrayable|array $vector): OracleVector
    {
        $this->ensureConnectionSupportsVectors();

        return new OracleVector($vector);
    }

    /**
     * Create a bindable Oracle spatial value from well-known text.
     */
    public function spatialValue(string $wkt, ?int $srid = null): OracleGeometry
    {
        return new OracleGeometry($wkt, $srid);
    }

    /**
     * Add a vector distance selection to the query.
     *
     * @param  ExpressionContract|string  $column
     * @param  Collection<int, float>|Arrayable<int, float>|array<int, float>|string  $vector
     *
     * @throws JsonException
     */
    public function selectVectorDistance($column, $vector, $as = null, string $metric = 'cosine'): static
    {
        $this->ensureConnectionSupportsVectors();
        $this->addBinding($this->encodeVector($vector), 'select');

        $alias = $this->grammar->wrap($as ?? (is_string($column) ? $column.'_distance' : 'distance'));

        return $this->addSelect(new Expression(
            $this->compileVectorDistance($column, $metric)." as {$alias}"
        ));
    }

    /**
     * Add a vector distance constraint to the query.
     *
     * @param  ExpressionContract|string  $column
     * @param  Collection<int, float>|Arrayable<int, float>|array<int, float>|string  $vector
     *
     * @throws JsonException
     */
    public function whereVectorDistanceLessThan(
        $column,
        $vector,
        $maxDistance,
        $boolean = 'and',
        string $metric = 'cosine'
    ): static {
        $this->ensureConnectionSupportsVectors();

        return $this->whereRaw(
            $this->compileVectorDistance($column, $metric).' <= ?',
            [$this->encodeVector($vector), $maxDistance],
            $boolean
        );
    }

    /**
     * Add an alternative vector distance constraint to the query.
     *
     * @param  ExpressionContract|string  $column
     * @param  Collection<int, float>|Arrayable<int, float>|array<int, float>|string  $vector
     *
     * @throws JsonException
     */
    public function orWhereVectorDistanceLessThan(
        $column,
        $vector,
        $maxDistance,
        string $metric = 'cosine'
    ): static {
        return $this->whereVectorDistanceLessThan($column, $vector, $maxDistance, 'or', $metric);
    }

    /**
     * Order the query by vector distance.
     *
     * @param  ExpressionContract|string  $column
     * @param  Collection<int, float>|Arrayable<int, float>|array<int, float>|string  $vector
     *
     * @throws JsonException
     */
    public function orderByVectorDistance($column, $vector, string $metric = 'cosine'): static
    {
        $this->ensureConnectionSupportsVectors();

        return $this->orderByRaw(
            $this->compileVectorDistance($column, $metric).' asc',
            [$this->encodeVector($vector)]
        );
    }

    /**
     * Add a WKT representation of a spatial column to the query.
     *
     * @param  ExpressionContract|string  $column
     */
    public function selectSpatialAsText($column, ?string $as = null): static
    {
        $alias = $this->grammar->wrap($as ?? (is_string($column) ? $column.'_wkt' : 'wkt'));

        return $this->addSelect(new Expression(
            'SDO_UTIL.TO_WKTGEOMETRY('.$this->grammar->wrap($column).") as {$alias}"
        ));
    }

    /**
     * Add the distance from a WKT geometry to the query.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function selectSpatialDistance(
        $column,
        $geometry,
        ?string $as = null,
        float $tolerance = 0.005,
        ?string $unit = null,
        ?int $srid = null
    ): static {
        [$sql, $bindings] = $this->compileSpatialDistance(
            $column, $geometry, $tolerance, $unit, $srid
        );

        $this->addBinding($bindings, 'select');
        $alias = $this->grammar->wrap($as ?? (is_string($column) ? $column.'_distance' : 'distance'));

        return $this->addSelect(new Expression("{$sql} as {$alias}"));
    }

    /**
     * Add an Oracle Spatial relationship constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function whereSpatialRelation(
        $column,
        $geometry,
        string $relation = 'ANYINTERACT',
        string $boolean = 'and',
        ?int $srid = null
    ): static {
        [$geometrySql, $bindings] = $this->compileSpatialGeometry($geometry, $srid);

        return $this->whereRaw(
            'SDO_RELATE('.$this->grammar->wrap($column).", {$geometrySql}, ?) = 'TRUE'",
            [...$bindings, 'mask='.strtoupper($relation)],
            $boolean
        );
    }

    /**
     * Add an alternative Oracle Spatial relationship constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function orWhereSpatialRelation(
        $column,
        $geometry,
        string $relation = 'ANYINTERACT',
        ?int $srid = null
    ): static {
        return $this->whereSpatialRelation($column, $geometry, $relation, 'or', $srid);
    }

    /**
     * Add a spatial intersection constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function whereSpatialIntersects(
        $column,
        $geometry,
        string $boolean = 'and',
        ?int $srid = null
    ): static {
        return $this->whereSpatialRelation($column, $geometry, 'ANYINTERACT', $boolean, $srid);
    }

    /**
     * Add a spatial containment constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function whereSpatialContains(
        $column,
        $geometry,
        string $boolean = 'and',
        ?int $srid = null
    ): static {
        return $this->whereSpatialRelation($column, $geometry, 'CONTAINS', $boolean, $srid);
    }

    /**
     * Add a spatial within-distance constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function whereSpatialWithinDistance(
        $column,
        $geometry,
        float $distance,
        ?string $unit = null,
        string $boolean = 'and',
        ?int $srid = null
    ): static {
        [$geometrySql, $bindings] = $this->compileSpatialGeometry($geometry, $srid);
        $parameters = 'distance='.$distance.($unit === null ? '' : ' unit='.strtoupper($unit));

        return $this->whereRaw(
            'SDO_WITHIN_DISTANCE('.$this->grammar->wrap($column).", {$geometrySql}, ?) = 'TRUE'",
            [...$bindings, $parameters],
            $boolean
        );
    }

    /**
     * Add an alternative spatial within-distance constraint.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function orWhereSpatialWithinDistance(
        $column,
        $geometry,
        float $distance,
        ?string $unit = null,
        ?int $srid = null
    ): static {
        return $this->whereSpatialWithinDistance($column, $geometry, $distance, $unit, 'or', $srid);
    }

    /**
     * Restrict and order the query to the nearest spatial values.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function whereSpatialNearestTo(
        $column,
        $geometry,
        int $neighbors,
        string $boolean = 'and',
        ?int $srid = null,
        int $label = 1
    ): static {
        if ($neighbors < 1) {
            throw new InvalidArgumentException('The number of nearest spatial values must be at least one.');
        }

        if ($label < 1) {
            throw new InvalidArgumentException('The spatial nearest-neighbor label must be at least one.');
        }

        [$geometrySql, $bindings] = $this->compileSpatialGeometry($geometry, $srid);

        $this->whereRaw(
            'SDO_NN('.$this->grammar->wrap($column).", {$geometrySql}, ?, {$label}) = 'TRUE'",
            [...$bindings, 'sdo_num_res='.$neighbors],
            $boolean
        );

        return $this->orderByRaw("SDO_NN_DISTANCE({$label}) asc");
    }

    /**
     * Order the query by spatial distance.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     */
    public function orderBySpatialDistance(
        $column,
        $geometry,
        float $tolerance = 0.005,
        ?string $unit = null,
        ?int $srid = null
    ): static {
        [$sql, $bindings] = $this->compileSpatialDistance(
            $column, $geometry, $tolerance, $unit, $srid
        );

        return $this->orderByRaw($sql.' asc', $bindings);
    }

    /**
     * Remove expressions and normalize Oracle value objects in a list of bindings.
     *
     * @param  array<mixed>  $bindings
     * @return list<mixed>
     */
    public function cleanBindings(array $bindings)
    {
        return array_map(
            static fn ($binding) => $binding instanceof OracleVector || $binding instanceof OracleGeometry
                ? (string) $binding
                : $binding,
            parent::cleanBindings($bindings)
        );
    }

    /**
     * Ensure the Oracle connection supports native vector queries.
     */
    protected function ensureConnectionSupportsVectors(): void
    {
        if (! $this->connection instanceof Oci8Connection
            || ! $this->connection->isVersionAboveOrEqual('23c')) {
            throw new RuntimeException('Vector distance queries require Oracle 23ai or newer.');
        }
    }

    /**
     * Encode a vector query value for Oracle.
     *
     * @param  Collection<int, float>|Arrayable<int, float>|array<int, float>|string  $vector
     *
     * @throws JsonException
     */
    protected function encodeVector($vector): string
    {
        if (is_string($vector)) {
            $vector = Str::of($vector)->toEmbeddings(cache: true);
        }

        return json_encode(
            $vector instanceof Arrayable ? $vector->toArray() : $vector,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * Compile an Oracle vector distance expression.
     *
     * @param  ExpressionContract|string  $column
     */
    protected function compileVectorDistance($column, string $metric): string
    {
        $metric = match (strtolower($metric)) {
            'cosine', 'vector_cosine_ops' => 'COSINE',
            'dot', 'inner_product', 'vector_ip_ops' => 'DOT',
            'euclidean', 'l2', 'vector_l2_ops' => 'EUCLIDEAN',
            'euclidean_squared', 'l2_squared', 'l2sq', 'vector_l2sq_ops' => 'EUCLIDEAN_SQUARED',
            'manhattan', 'l1', 'vector_l1_ops' => 'MANHATTAN',
            'hamming', 'vector_hamming_ops' => 'HAMMING',
            'jaccard', 'vector_jaccard_ops' => 'JACCARD',
            default => throw new InvalidArgumentException("Unsupported Oracle vector distance metric [{$metric}]."),
        };

        return 'VECTOR_DISTANCE('.$this->grammar->wrap($column).", TO_VECTOR(?), {$metric})";
    }

    /**
     * Compile a WKT geometry or spatial expression.
     *
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     * @return array{string, list<mixed>}
     */
    protected function compileSpatialGeometry($geometry, ?int $srid): array
    {
        if ($geometry instanceof ExpressionContract) {
            if ($srid !== null) {
                throw new InvalidArgumentException('An SRID cannot be applied to a raw spatial expression.');
            }

            return [(string) $this->grammar->getValue($geometry), []];
        }

        if ($geometry instanceof OracleGeometry) {
            if ($srid !== null) {
                throw new InvalidArgumentException('Pass the SRID to spatialValue instead of the spatial query method.');
            }

            $srid = $geometry->srid;
            $geometry = $geometry->wkt;
        }

        $sridSql = $srid ?? 'NULL';

        return ["MDSYS.SDO_GEOMETRY(?, {$sridSql})", [$geometry]];
    }

    /**
     * Compile an Oracle Spatial distance expression.
     *
     * @param  ExpressionContract|string  $column
     * @param  ExpressionContract|OracleGeometry|string  $geometry
     * @return array{string, list<mixed>}
     */
    protected function compileSpatialDistance(
        $column,
        $geometry,
        float $tolerance,
        ?string $unit,
        ?int $srid
    ): array {
        [$geometrySql, $bindings] = $this->compileSpatialGeometry($geometry, $srid);
        $sql = 'SDO_GEOM.SDO_DISTANCE('.$this->grammar->wrap($column).", {$geometrySql}, ?";
        $bindings[] = $tolerance;

        if ($unit !== null) {
            $sql .= ', ?';
            $bindings[] = 'unit='.strtoupper($unit);
        }

        return [$sql.')', $bindings];
    }

    /**
     * Insert a new record and get the value of the primary key.
     */
    public function insertLob(array $values, array $binaries, string $sequence = 'id'): int
    {
        /** @var OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql = $grammar->compileInsertLob($this, $values, $binaries, $sequence);

        $values = $this->cleanBindings($values);
        $binaries = $this->cleanBindings($binaries);

        /** @var OracleProcessor $processor */
        $processor = $this->processor;

        return $processor->saveLob($this, $sql, $values, $binaries);
    }

    /**
     * Update a new record with blob field.
     */
    public function updateLob(array $values, array $binaries, string $sequence = 'id'): bool
    {
        $bindings = array_values(array_merge($values, $this->getBindings()));

        /** @var OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql = $grammar->compileUpdateLob($this, $values, $binaries, $sequence);

        $values = $this->cleanBindings($bindings);
        $binaries = $this->cleanBindings($binaries);

        /** @var OracleProcessor $processor */
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
     * @param  \Closure|Builder|string  $table
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
     * @param  \Closure|Builder|string  $query
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
     * @param  \Closure|Builder|string  $query
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
     * @param  \Closure|Builder|string  $query
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

    /**
     * Add a lateral join clause to the query.
     *
     * @param  \Closure|Builder|string  $query
     */
    public function joinLateral($query, string $as, string $type = 'inner'): static
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '('.$query.') '.$this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        $this->joins[] = $this->newJoinLateralClause($this, $type, new Expression($expression));

        return $this;
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
     * Run a pagination count query.
     *
     * @param  array<string|ExpressionContract>  $columns
     * @return array<mixed>
     */
    protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();

            if (is_null($clone->columns) && ! empty($this->joins)) {
                $clone->select($this->from.'.*');
            }

            return $this->newQuery()
                ->from(new Expression('('.$clone->toSql().') '.$this->grammar->wrap('aggregate_table')))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get()->all();
        }

        $without = $this->unions ? ['unionOrders', 'unionLimit', 'unionOffset'] : ['columns', 'orders', 'limit', 'offset'];

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['unionOrder'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }
}
