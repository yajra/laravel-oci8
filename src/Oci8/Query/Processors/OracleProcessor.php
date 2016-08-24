<?php

namespace Yajra\Oci8\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;

class OracleProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  Builder $query
     * @param  string $sql
     * @param  array $values
     * @param  string $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $id        = 0;
        $parameter = 0;
        $statement = $this->prepareStatement($query, $sql);
        $values    = $this->incrementBySequence($values, $sequence);
        $parameter = $this->bindValues($values, $statement, $parameter);
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, 10);
        $statement->execute();

        return (int) $id;
    }

    /**
     * Get prepared statement.
     *
     * @param Builder $query
     * @param string $sql
     * @return \PDOStatement|\Yajra\Pdo\Oci8
     */
    private function prepareStatement(Builder $query, $sql)
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $query->getConnection();
        $pdo        = $connection->getPdo();

        return $pdo->prepare($sql);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param array $values
     * @param string $sequence
     * @return array
     */
    protected function incrementBySequence(array $values, $sequence)
    {
        $builder     = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[4]['object'];
        $builderArgs = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[3]['args'];

        if (! isset($builderArgs[1][0][$sequence])) {
            if (method_exists($builder, 'getModel')) {
                $model = $builder->getModel();
                if ($model->sequence && $model->incrementing) {
                    $values[] = (int) $model->getConnection()->getSequence()->nextValue($model->sequence);
                }
            }
        }

        return $values;
    }

    /**
     * Bind values to PDO statement.
     *
     * @param array $values
     * @param \PDOStatement $statement
     * @param int $parameter
     * @return int
     */
    private function bindValues(&$values, $statement, $parameter)
    {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if (is_object($values[$i])) {
                $values[$i] = (string) $values[$i];
            }
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }

        return $parameter;
    }

    /**
     * Get PDO Type depending on value.
     *
     * @param mixed $value
     * @return int
     */
    private function getPdoType($value)
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Save Query with Blob returning primary key value.
     *
     * @param  Builder $query
     * @param  string $sql
     * @param  array $values
     * @param  array $binaries
     * @return int
     */
    public function saveLob(Builder $query, $sql, array $values, array $binaries)
    {
        $id        = 0;
        $parameter = 0;
        $statement = $this->prepareStatement($query, $sql);

        $parameter = $this->bindValues($values, $statement, $parameter);

        $countBinary = count($binaries);
        for ($i = 0; $i < $countBinary; $i++) {
            $statement->bindParam($parameter, $binaries[$i], PDO::PARAM_LOB, -1);
            $parameter++;
        }

        // bind output param for the returning clause.
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, 10);

        if (! $statement->execute()) {
            return false;
        }

        return (int) $id;
    }

    /**
     * Process the results of a column listing query.
     *
     * @param  array $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return $r->column_name;
        };

        return array_map($mapping, $results);
    }
}
