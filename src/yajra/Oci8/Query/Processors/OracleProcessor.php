<?php

namespace yajra\Oci8\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;
use PDOStatement;

class OracleProcessor extends Processor
{

    /**
     * DB Statement
     *
     * @var PDOStatement
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
        $counter = 0;
        $id = 0;

        // set PDO statement property
        $this->prepareStatement($query, $sql);
        $counter = $this->bindValuesAndReturnCounter($values, $counter);

        // bind output param for the returning clause
        $this->statement->bindParam($counter, $id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);

        // execute statement
        $this->statement->execute();

        return (int) $id;
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
        $counter = 0;
        $lob = [];
        $id = 0;

        // begin transaction
        $query->getConnection()->getPdo()->beginTransaction();

        // set PDO statement property
        $this->prepareStatement($query, $sql);
        $counter = $this->bindValuesAndReturnCounter($values, $counter);

        $binariesCount = count($binaries);
        for ($i = 0; $i < $binariesCount; $i++) {
            // bind blob descriptor
            $this->statement->bindParam($counter, $lob[$i], PDO::PARAM_LOB);
            $counter++;
        }

        // bind output param for the returning clause
        $this->statement->bindParam($counter, $id, PDO::PARAM_INT);

        // execute statement
        if ( ! $this->statement->execute()) {
            $query->getConnection()->getPdo()->rollBack();

            return false;
        }

        for ($i = 0; $i < $binariesCount; $i++) {
            // Discard the existing LOB contents
            if ( ! $lob[$i]->truncate()) {
                $query->getConnection()->getPdo()->rollBack();

                return false;
            }
            // save blob content
            if ( ! $lob[$i]->save($binaries[$i])) {
                $query->getConnection()->getPdo()->rollBack();

                return false;
            }
        }

        // commit statements
        $query->getConnection()->getPdo()->commit();

        return (int) $id;
    }

    /**
     * @param Builder $query
     * @param string $sql
     * @internal param $PDOStatement
     */
    protected function prepareStatement(Builder $query, $sql)
    {
        $this->statement = $query->getConnection()->getPdo()->prepare($sql);
    }

    /**
     * @param array $values
     * @param integer $counter
     * @return integer
     */
    protected function bindValuesAndReturnCounter(array $values, $counter)
    {
        // bind each parameter from the values array to their location
        foreach ($values as $value) {
            // try to determine type of result
            if (is_int($value)) {
                $param = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $param = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $param = PDO::PARAM_NULL;
            } elseif ($value instanceOf \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
                $param = PDO::PARAM_STR;
            } else {
                $param = PDO::PARAM_STR;
            }

            $this->statement->bindValue($counter, ($value), $param);
            // increment counter
            $counter++;
        }

        return $counter;
    }

}
