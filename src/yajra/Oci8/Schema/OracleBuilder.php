<?php namespace yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OracleBuilder extends Builder {

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

		$this->createAutoIncrementObjects($blueprint, $table);
	}

	/**
	 * create sequence and trigger for autoIncrement support
	 * @param  \Illuminate\Database\Schema\Blueprint $blueprint
	 * @param  string $table
	 * @return null
	 */
	protected function createAutoIncrementObjects($blueprint, $table)
	{
		// create sequence and trigger object
		$columns = $blueprint->getColumns();

		$col = "";
		// search for primary key / autoIncrement column
		foreach ($columns as $column) {
			// if column is autoIncrement set the primary col name
			if ($column->autoIncrement) {
				$col = $column->name;
				$start = isset($column->start) ? $column->start : 1;
			}
		}

		// if primary key col is set, create auto increment objects
		if (isset($col) and !empty($col)) {
			// add table prefix to table name
			$prefix = $this->connection->getTablePrefix();
			// create sequence for auto increment
			$sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
			$this->connection->createSequence($sequenceName, $start);
	        // create trigger for auto increment work around
	        $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
			$this->connection->createAutoIncrementTrigger($prefix . $table, $col, $triggerName, $sequenceName);
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
		$this->dropAutoIncrementObjects($table);
		parent::drop($table);
	}

	/**
	 * Indicate that the table should be dropped if it exists.
	 *
	 * @return \Illuminate\Support\Fluent
	 */
	public function dropIfExists($table)
	{
		$this->dropAutoIncrementObjects($table);
		parent::dropIfExists($table);
	}

	/**
	 * drop sequence and triggers if exists, autoincrement objects
	 * @param  string $table
	 * @return null
	 */
	protected function dropAutoIncrementObjects($table)
	{
		// drop sequence and trigger object
		$prefix = $this->connection->getTablePrefix();
		// get the actual primary column name from table
		$col = $this->connection->getPrimaryKey($prefix.$table);
		// if primary key col is set, drop auto increment objects
		if (isset($col) and !empty($col)) {
	      	// drop sequence for auto increment
			$sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
			$this->connection->dropSequence($sequenceName);
	        // drop trigger for auto increment work around
	        $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
			$this->connection->dropTrigger($triggerName);
		}
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

	/**
	 * Create an object name that limits to 30chars
	 * @param  string $prefix
	 * @param  string $table
	 * @param  string $col
	 * @param  string $type
	 * @return string
	 */
	private function createObjectName($prefix, $table, $col, $type)
	{
		// max object name length is 30 chars
		return substr($prefix . $table . '_' . $col . '_' . $type, 0, 30);
	}

}
