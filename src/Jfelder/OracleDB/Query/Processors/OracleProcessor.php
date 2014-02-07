<?php namespace Jfelder\OracleDB\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as Processor;

class OracleProcessor extends Processor 
{

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
        $counter = 0;
        $last_insert_id = 0;

        //Get PDO object 
        $pdo = $query->getConnection()->getPdo();

        // get PDO statment object
        $stmt = $pdo->prepare($sql);

        // PDO driver params are 1-based so ++ has to be before bindValue
        // OCI driver params are 0-based so no ++ before bindValue
        if (get_class($pdo) != 'Jfelder\OracleDB\OCI_PDO\OCI')
            $counter++;

        // bind each parameter from the values array to their location in the 
        foreach($values as $k => $v) {
            $stmt->bindValue($counter++, $v, $this->bindType($v));
        }

        // bind output param for the returning cluase
        $stmt->bindParam($counter, $last_insert_id, \PDO::PARAM_INT|\PDO::PARAM_INPUT_OUTPUT, 8);

        // execute statement
        $stmt->execute();

        return (int) $last_insert_id;
    }

    /*
     * Determine parameter type passed in
     * 
     * @param mixed $param
     * @return \PDO::PARAM_* type
     */
    private function bindType($param) 
    {
        if(is_int($param)) {
            $param = \PDO::PARAM_INT;
        } elseif(is_bool($param)) {
            $param = \PDO::PARAM_BOOL;
        } elseif(is_null($param)) {
            $param = \PDO::PARAM_NULL;
        } else {
            $param = \PDO::PARAM_STR;
        }

        return $param;
    }

}