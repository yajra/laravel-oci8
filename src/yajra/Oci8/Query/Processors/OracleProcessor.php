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
        $stmt->bindParam($counter, $last_insert_id, \PDO::PARAM_INT, 8);

        // execute statement
        $stmt->execute();

        return (int) $last_insert_id;
    }

}