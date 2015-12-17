<?php

namespace Yajra\Oci8\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;

class OracleProcessor extends Processor
{
    /**
     * DB Statement
     *
     * @var \PDOStatement
     */
    protected $statement;

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
        $parameter = 0;
        $id        = 0;

        $this->prepareStatement($query, $sql);
        for ($i = 0; $i < count($values); $i++) {
            ${'param' . $i} = $values[$i];
            $this->bindValue($parameter, ${'param' . $i});
            $parameter++;
        }
        $this->bindValue($parameter, $id);
        $this->statement->execute();

        return (int) $id;
    }

    /**
     * @param Builder $query
     * @param string $sql
     * @internal param $PDOStatement
     */
    private function prepareStatement(Builder $query, $sql)
    {
        $pdo             = $query->getConnection()->getPdo();
        $this->statement = $pdo->prepare($sql);
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
        $parameter = 0;
        $lob       = [];
        $id        = 0;

        // begin transaction
        $pdo           = $query->getConnection()->getPdo();
        $inTransaction = $pdo->inTransaction();
        if (! $inTransaction) {
            $pdo->beginTransaction();
        }

        $this->prepareStatement($query, $sql);
        foreach ($values as $value) {
            $this->bindValue($parameter, $value);
            $parameter++;
        }

        $binariesCount = count($binaries);
        for ($i = 0; $i < $binariesCount; $i++) {
            // bind blob descriptor
            $this->statement->bindParam($parameter, $lob[$i], PDO::PARAM_LOB);
            $parameter++;
        }

        // bind output param for the returning clause
        $this->statement->bindParam($parameter, $id, PDO::PARAM_INT);

        // execute statement
        if (! $this->statement->execute()) {
            $pdo->rollBack();

            return false;
        }

        for ($i = 0; $i < $binariesCount; $i++) {
            // Discard the existing LOB contents
            if (! $lob[$i]->truncate()) {
                $pdo->rollBack();

                return false;
            }
            // save blob content
            if (! $lob[$i]->save($binaries[$i])) {
                $pdo->rollBack();

                return false;
            }
        }

        if (! $inTransaction) {
            // commit statements
            $pdo->commit();
        }

        return (int) $id;
    }

    /**
     * Bind value to an statement
     *
     * @param int $parameter
     * @param mixed $variable
     */
    private function bindValue($parameter, &$variable)
    {
        $param     = PDO::PARAM_STR;
        $maxLength = -1;

        if (is_int($variable)) {
            $param     = PDO::PARAM_INT;
            $maxLength = 10;
        } elseif (is_bool($variable)) {
            $param = PDO::PARAM_BOOL;
        } elseif (is_null($variable)) {
            $param = PDO::PARAM_NULL;
        } elseif ($variable instanceof \DateTimeInterface) {
            $variable = $variable->format('Y-m-d H:i:s');
        }

        $this->statement->bindParam($parameter, $variable, $param, $maxLength);
    }
}
