<?php namespace yajra\Oci8\Query;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use yajra\Oci8\Query\Grammars\OracleGrammar as Grammar;
use yajra\Oci8\Query\Processors\OracleProcessor as Processor;

class OracleBuilder extends Builder {

	/**
	 * Insert a new record and get the value of the primary key.
	 *
	 * @param  array   $values
	 * @param  array   $binaries
	 * @param  string   $sequence
	 * @return int
	 */
	public function insertLob(array $values, $binaries, $sequence = null)
	{
		$sql = $this->grammar->compileInsertLob($this, $values, $binaries, $sequence);

		$values = $this->cleanBindings($values);
		$binaries = $this->cleanBindings($binaries);

		return $this->processor->processInsertLob($this, $sql, $values, $binaries);
	}

}
