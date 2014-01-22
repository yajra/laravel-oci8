<?php
namespace yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;

class OracleBuilder extends \Illuminate\Database\Schema\Builder {

	/**
	 * Create a new table on the schema.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @return \Illuminate\Database\Schema\Blueprint
	 */
	public function create($table, Closure $callback)
	{
		$blueprint = $this->createBlueprint($table);

		$blueprint->create();

		$callback($blueprint);

		$this->build($blueprint);

		// *** auto increment hack ***
		// create sequence and trigger object
		$db = $this->connection;
		$columns = $blueprint->getColumns();

		// search for primary key / autoIncrement column
		foreach ($columns as $column) {
			// if column is autoIncrement set the primary col name
			if ($column->autoIncrement) {
				$col = $column->name;
			}
		}

		// if primary key col is set, create auto increment objects
		if (isset($col)) {
	      	// create sequence for auto increment
			$db->createSequence("{$table}_{$col}_seq");
	        // create trigger for auto increment work around
			$db->createAutoIncrementTrigger($table, $col);
		}

	}

}

