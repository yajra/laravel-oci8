<?php namespace yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint;

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
		$columns = $blueprint->getColumns();

		$col = "";
		// search for primary key / autoIncrement column
		foreach ($columns as $column) {
			// if column is autoIncrement set the primary col name
			if ($column->autoIncrement) {
				$col = $column->name;
			}
		}

		// add table prefix to table name
		$prefix = $this->connection->getTablePrefix();
		$table = $prefix . $table;
		// if primary key col is set, create auto increment objects
		if (isset($col) and !empty($col)) {
	      	// create sequence for auto increment
			$this->connection->createSequence("{$table}_{$col}_seq");
	        // create trigger for auto increment work around
			$this->connection->createAutoIncrementTrigger($table, $col);
		}

	}

	/**
	 * Drop a table from the schema.
	 *
	 * @param  string  $table
	 * @return \Illuminate\Database\Schema\Blueprint
	 */
	public function drop($table)
	{
		// *** auto increment hack rollback ***
		// drop sequence and trigger object
		$prefix = $this->connection->getTablePrefix();
		// get the actual primary column name from table
		$col = $this->connection->getPrimaryKey($prefix.$table);
		// if primary key col is set, drop auto increment objects
		if (isset($col) and !empty($col)) {
	      	// drop sequence for auto increment
			$this->connection->dropSequence("{$prefix}{$table}_{$col}_seq");
	        // drop trigger for auto increment work around
			$this->connection->dropTrigger("{$prefix}{$table}_{$col}_trg");
		}

		$blueprint = $this->createBlueprint($table);

		$blueprint->drop();

		$this->build($blueprint);

	}

	/**
	 * Create a new command set with a Closure.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @return \Illuminate\Database\Schema\Blueprint
	 */
	protected function createBlueprint($table, Closure $callback = null)
	{
		$blueprint = new OracleBlueprint($table, $callback);
		$blueprint->setTablePrefix($this->connection->getTablePrefix());
		return $blueprint;
	}

}