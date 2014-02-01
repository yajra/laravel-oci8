<?php namespace Jfelder\OracleDB\Query\Processors;

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
                $counter = 1;
                $last_insert_id = 0;
                
                // get PDO statment object
                $stmt = $query->getConnection()->getPdo()->prepare($sql);
                
                // bind each parameter from the values array to their location
                // these are 1-based so ++ has to be before bindValue
                foreach($values as $k => $v)
                {
                    // try to determine type of result
                    if(is_int($v))
                        $param = \PDO::PARAM_INT;
                    elseif(is_bool($v))
                        $param = \PDO::PARAM_BOOL;
                    elseif(is_null($v))
                        $param = \PDO::PARAM_NULL;
                    else
                        $param = \PDO::PARAM_STR;

                    $stmt->bindValue($counter++, $values[$k], $param);
                }

                // bind output param for the returning cluase
                $stmt->bindParam($counter, $last_insert_id, \PDO::PARAM_INT|\PDO::PARAM_INPUT_OUTPUT, 8);

                // execute statement
                $stmt->execute();
                
		return (int) $last_insert_id;
	}

}