<?php

namespace Yajra\Oci8\Query\Processors;

use DateTime;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;
use PDOStatement;

class OracleProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  string  $sql
     * @param  array  $values
     * @param  null|string  $sequence
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null): int
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();
        $start = microtime(true);

        $id = 0;
        $parameter = 1;
        $statement = $this->prepareStatement($query, $sql);
        $values = $this->incrementBySequence($values, $sequence);
        $parameter = $this->bindValues($query, $values, $statement, $parameter);
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, -1);
        $statement->execute();

        $connection->logQuery($sql, $values, $start);

        return $id;
    }

    /**
     * Get prepared statement.
     */
    private function prepareStatement(Builder $query, string $sql): PDOStatement|false
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        return $pdo->prepare($sql);
    }

    /**
     * Insert a new record and get the value of the primary key.
     */
    protected function incrementBySequence(array $values, ?string $sequence = null): array
    {
        $builder = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[3]['object'];
        $builderArgs = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[2]['args'];

        if (! isset($builderArgs[1][0][$sequence])) {
            if ($builder instanceof EloquentBuilder) {
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = $builder->getModel();

                /** @var \Yajra\Oci8\Oci8Connection $connection */
                $connection = $model->getConnection();
                if (isset($model->sequence) && $model->incrementing) {
                    $values[] = $connection->getSequence()->nextValue($model->sequence);
                }
            }
        }

        return $values;
    }

    /**
     * Bind values to PDO statement.
     */
    private function bindValues(Builder $query, array &$values, PDOStatement $statement, int $parameter): int
    {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if (is_object($values[$i])) {
                if ($values[$i] instanceof DateTime) {
                    $values[$i] = $values[$i]->format($query->grammar->getDateFormat());
                } else {
                    $values[$i] = (string) $values[$i];
                }
            }
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }

        return $parameter;
    }

    /**
     * Get PDO Type depending on value.
     */
    private function getPdoType(mixed $value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Save Query with Blob returning primary key value.
     */
    public function saveLob(Builder $query, string $sql, array $values, array $binaries): int
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();
        $start = microtime(true);

        $id = 0;
        $parameter = 1;
        $statement = $this->prepareStatement($query, $sql);

        $parameter = $this->bindValues($query, $values, $statement, $parameter);

        $countBinary = count($binaries);
        for ($i = 0; $i < $countBinary; $i++) {
            $statement->bindParam($parameter, $binaries[$i], PDO::PARAM_LOB, -1);
            $parameter++;
        }

        // bind output param for the returning clause.
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, -1);

        if (! $statement->execute()) {
            return 0;
        }

        $connection->logQuery($sql, $values, $start);

        return $id;
    }

    /**
     * Process the results of a column listing query.
     */
    public function processColumnListing(array $results): array
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return strtolower((string) $r->column_name);
        };

        return array_map($mapping, $results);
    }

    /**
     * Process the results of a columns query.
     *
     * @param  array  $results
     */
    public function processColumns($results): array
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $type = strtolower((string) $result->type);
            $precision = (int) $result->precision;
            $places = (int) $result->places;
            $length = (int) $result->data_length;

            switch ($typeName = strtolower((string) $result->type_name)) {
                case 'number':
                    if ($precision === 19 && $places === 0) {
                        $type = 'bigint';
                    } elseif ($precision === 10 && $places === 0) {
                        $type = 'int';
                    } elseif ($precision === 5 && $places === 0) {
                        $type = 'smallint';
                    } elseif ($precision === 1 && $places === 0) {
                        $type = 'boolean';
                    } elseif ($places > 0) {
                        $type = 'decimal';
                    }

                    break;

                case 'varchar':
                case 'varchar2':
                case 'nvarchar2':
                case 'char':
                case 'nchar':
                    $length = (int) $result->char_length;
                    break;
                default:
                    $type = $typeName;
            }

            return [
                'name' => strtolower((string) $result->name),
                'type_name' => strtolower((string) $result->type_name),
                'type' => $type,
                'nullable' => (bool) $result->nullable,
                'default' => $result->default,
                'auto_increment' => (bool) $result->auto_increment,
                'comment' => $result->comment != '' ? $result->comment : null,
                'length' => $length,
                'precision' => $precision,
            ];
        }, $results);
    }

    /**
     * Process the results of a columns query.
     *
     * @param  array  $results
     */
    public function processForeignKeys($results): array
    {
        return array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => strtolower((string) $result->name),
                'columns' => explode(',', strtolower((string) $result->columns)),
                'foreign_schema' => strtolower((string) $result->foreign_schema),
                'foreign_table' => strtolower((string) $result->foreign_table),
                'foreign_columns' => explode(',', strtolower((string) $result->foreign_columns)),
                'on_update' => strtolower((string) $result->on_update),
                'on_delete' => $result->on_delete,
            ];
        }, $results);
    }

    /**
     * Process the results of an indexes query.
     *
     * @param  array  $results
     */
    public function processIndexes($results): array
    {
        $collection = array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => $name = strtolower((string) $result->name),
                'columns' => $result->columns,
                'type' => strtolower((string) $result->type),
                'unique' => (bool) $result->unique,
                'primary' => str_contains($name, '_pk'),
            ];
        }, $results);

        return collect($collection)->groupBy('name')->map(fn ($items) => [
            'name' => $items->first()['name'],
            'columns' => $items->pluck('columns')->map(fn ($item) => strtolower((string) $item))->all(),
            'type' => $items->first()['type'],
            'unique' => $items->first()['unique'],
            'primary' => $items->first()['primary'],
        ])->values()->all();
    }
}
