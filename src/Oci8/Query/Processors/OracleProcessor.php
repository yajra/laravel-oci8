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

        for ($i = 0; $i < count($values); $i++) {
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, 10);
        $statement->execute();

        return (int) $id;
    }

    /**
     * Get prepared statement.
     *
     * @param Builder $query
     * @param string $sql
     * @return \PDOStatement | \Yajra\Pdo\Oci8
     */
    private function prepareStatement(Builder $query, $sql)
    {
        $pdo = $query->getConnection()->getPdo();

        return $pdo->prepare($sql);
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
     * save Query with Blob returning primary key value
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

        // bind values.
        for ($i = 0; $i < count($values); $i++) {
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }

        // bind blob fields.
        for ($i = 0; $i < count($binaries); $i++) {
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
}
