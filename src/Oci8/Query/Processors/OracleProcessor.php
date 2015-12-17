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
            ${'param' . $i} = $values[$i];
            $statement->bindParam($parameter, ${'param' . $i});
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
            ${'param' . $i} = $values[$i];
            $statement->bindParam($parameter, ${'param' . $i});
            $parameter++;
        }

        // bind blob fields.
        for ($i = 0; $i < count($binaries); $i++) {
            ${'binary' . $i} = $binaries[$i];
            $statement->bindParam($parameter, ${'binary' . $i}, PDO::PARAM_LOB, -1);
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
