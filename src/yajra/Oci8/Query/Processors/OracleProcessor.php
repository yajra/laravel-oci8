<?php namespace yajra\Oci8\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;

class OracleProcessor extends Processor {

    /**
     * DB Statement
     * @var Oci8Statement
     */
    protected $statement;

    /**
     * Process an "insert get ID" query.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $counter = 0;
        $id = 0;

        // get PDO statement object
        $this->statement = $query->getConnection()->getPdo()->prepare($sql);

        // bind each parameter from the values array to their location
        foreach ($values as $value)
        {
            // try to determine type of result
            if (is_int($value))
               $param = PDO::PARAM_INT;
            elseif (is_bool($value))
               $param = PDO::PARAM_BOOL;
            elseif (is_null($value))
               $param = PDO::PARAM_NULL;
            else
               $param = PDO::PARAM_STR;

            $this->statement->bindValue($counter, ($value), $param);
            // increment counter
            $counter++;
        }

        // bind output param for the returning cluase
        $this->statement->bindParam($counter, $id, PDO::PARAM_INT);

        // execute statement
        $this->statement->execute();

        return (int) $id;
    }

    /**
     * save Query with Blob returning primary key value
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  array  $binaries
     * @return int
     */
    public function saveLob(Builder $query, $sql, array $values, array $binaries)
    {
        $counter = 0;
        $lob = array();
        $id = 0;

        // begin transaction
        $query->getConnection()->getPdo()->beginTransaction();

        // get PDO statement object
        $this->statement = $query->getConnection()->getPdo()->prepare($sql);

        // bind each parameter from the values array to their location
        foreach($values as $value)
        {
            // try to determine type of result
            if (is_int($value))
               $param = PDO::PARAM_INT;
            elseif (is_bool($value))
               $param = PDO::PARAM_BOOL;
            elseif (is_null($value))
               $param = PDO::PARAM_NULL;
            else
               $param = PDO::PARAM_STR;

            $this->statement->bindValue($counter, ($value), $param);
            // increment counter
            $counter++;
        }

        for ($i=0; $i < count($binaries); $i++)
        {
            // bind blob decriptor
            $this->statement->bindParam($counter, $lob[$i], PDO::PARAM_LOB);
            $counter++;
        }

        // bind output param for the returning clause
        $this->statement->bindParam($counter, $id, PDO::PARAM_INT);

        // execute statement
        if (! $this->statement->execute())
        {
            $query->getConnection()->getPdo()->rollBack();
            return false;
        }

        for ($i=0; $i < count($binaries); $i++)
        {
            // Discard the existing LOB contents
            if (! $lob[$i]->truncate())
            {
                $query->getConnection()->getPdo()->rollBack();
                return false;
            }
            // save blob content
            if (! $lob[$i]->save($binaries[$i]))
            {
                $query->getConnection()->getPdo()->rollBack();
                return false;
            }
        }

        // commit statements
        $query->getConnection()->getPdo()->commit();

        return (int) $id;
    }

}
