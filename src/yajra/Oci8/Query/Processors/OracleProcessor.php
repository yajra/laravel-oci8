<?php namespace yajra\Oci8\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as Processor;

class OracleProcessor extends Processor {

    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $sequence = $sequence ?: 'id';
        $counter = 0;
        $last_insert_id = 0;

        // get PDO statement object
        $stmt = $query->getConnection()->getPdo()->prepare($sql);

        // bind each parameter from the values array to their location
        foreach($values as $value)
        {
            // try to determine type of result
            if(is_int($value))
               $param = \PDO::PARAM_INT;
            elseif(is_bool($value))
               $param = \PDO::PARAM_BOOL;
            elseif(is_null($value))
               $param = \PDO::PARAM_NULL;
            else
               $param = \PDO::PARAM_STR;

            $stmt->bindValue($counter, ($value), $param);
            // increment counter
            $counter++;
        }

        // bind output param for the returning cluase
        $stmt->bindParam($counter, $last_insert_id, \PDO::PARAM_INT);

        // execute statement
        $stmt->execute();

        return (int) $last_insert_id;
    }

    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  array  $binaries
     * @return int
     */
    public function processInsertLob(Builder $query, $sql, $values, $binaries)
    {
        $counter = 0;
        $lob = array();
        $last_insert_id = 0;

        // begin transaction
        $query->getConnection()->getPdo()->beginTransaction();

        // get PDO statement object
        $stmt = $query->getConnection()->getPdo()->prepare($sql);

        // bind each parameter from the values array to their location
        foreach($values as $value)
        {
            // try to determine type of result
            if(is_int($value))
               $param = \PDO::PARAM_INT;
            elseif(is_bool($value))
               $param = \PDO::PARAM_BOOL;
            elseif(is_null($value))
               $param = \PDO::PARAM_NULL;
            else
               $param = \PDO::PARAM_STR;

            $stmt->bindValue($counter, ($value), $param);
            // increment counter
            $counter++;
        }

        for ($i=0; $i < count($binaries); $i++) {
            // bind blob decriptor
            $stmt->bindParam($counter, $lob[$i], \PDO::PARAM_LOB);
            $counter++;
        }

        // bind output param for the returning clause
        $stmt->bindParam($counter, $last_insert_id, \PDO::PARAM_INT);

        // execute statement
        if ( !$stmt->execute() ) {
            $query->getConnection()->getPdo()->rollBack();
            return false;
        }

        for ($i=0; $i < count($binaries); $i++) {
            // save blob content
            if ( !$lob[$i]->save($binaries[$i]) ) {
                $query->getConnection()->getPdo()->rollBack();
                return false;
            }
        }

        // commit statements
        $query->getConnection()->getPdo()->commit();

        return (int) $last_insert_id;
    }

}