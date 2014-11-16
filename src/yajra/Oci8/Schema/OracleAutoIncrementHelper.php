<?php namespace yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class OracleAutoIncrementHelper {

	protected $connection;

	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * create sequence and trigger for autoIncrement support
	 *
	 * @param  Blueprint $blueprint
	 * @param  string $table
	 * @return null
	 */
	public function createAutoIncrementObjects(Blueprint $blueprint, $table)
	{
		$column = $this->getQualifiedAutoIncrementColumn($blueprint);

		// return if no qualified AI column
		if (is_null($column)) return;

		$col = $column->name;
		$start = isset($column->start) ? $column->start : 1;

		// get table prefix
		$prefix = $this->connection->getTablePrefix();

		// create sequence for auto increment
		$sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
		$this->createSequence($sequenceName, $start, $column->nocache);

        // create trigger for auto increment work around
        $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
		$this->createAutoIncrementTrigger($prefix . $table, $col, $triggerName, $sequenceName);
	}

	/**
	 * get qualified autoincrement column
	 *
	 * @param  Blueprint $blueprint
	 * @return Fluent|null
	 */
	public function getQualifiedAutoIncrementColumn(Blueprint $blueprint)
	{
		$columns = $blueprint->getColumns();

		// search for primary key / autoIncrement column
		foreach ($columns as $column)
        {
			// if column is autoIncrement set the primary col name
			if ($column->autoIncrement)
            {
				return $column;
			}
		}

		return null;
	}

	/**
	 * drop sequence and triggers if exists, autoincrement objects
	 * @param  string $table
	 * @return null
	 */
	public function dropAutoIncrementObjects($table)
	{
		// drop sequence and trigger object
		$prefix = $this->connection->getTablePrefix();
		// get the actual primary column name from table
		$col = $this->getPrimaryKey($prefix . $table);
		// if primary key col is set, drop auto increment objects
		if (isset($col) and !empty($col))
        {
	      	// drop sequence for auto increment
			$sequenceName = $this->createObjectName($prefix, $table, $col, 'seq');
			$this->dropSequence($sequenceName);

	        // drop trigger for auto increment work around
	        $triggerName = $this->createObjectName($prefix, $table, $col, 'trg');
			$this->dropTrigger($triggerName);
		}
	}

	/**
	 * function to create oracle sequence
	 *
	 * @param  string  $name
	 * @param  integer $start
	 * @param  boolean $nocache
	 * @return boolean
	 */
	public function createSequence($name, $start = 1, $nocache = false)
	{
		if (!$name) return false;

		$nocache = $nocache ? 'nocache' : '';

		return $this->connection->statement("create sequence {$name} start with {$start} {$nocache}");
	}

	/**
	 * function to safely drop sequence db object
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function dropSequence($name)
	{
		// check if a valid name and sequence exists
		if (!$name or !$this->checkSequence($name)) return false;

		return $this->connection->statement("
			declare
				e exception;
				pragma exception_init(e,-02289);
			begin
				execute immediate 'drop sequence {$name}';
			exception
			when e then
				null;
			end;");
	}

	/**
	 * function to get oracle sequence last inserted id
	 *
	 * @param  string $name
	 * @return integer
	 */
	public function lastInsertId($name)
	{
		// check if a valid name and sequence exists
		if (!$name or !$this->checkSequence($name)) return 0;

		return $this->connection->selectOne("select {$name}.currval as id from dual")->id;
	}

	/**
	 * get sequence next value
	 *
	 * @param  string $name
	 * @return integer
	 */
	public function nextSequenceValue($name)
	{
		if (!$name) return 0;

		return $this->connection->selectOne("SELECT $name.NEXTVAL as id FROM DUAL")->id;
	}

	/**
	 * same function as lastInsertId. added for clarity with oracle sql statement.
	 *
	 * @param  string $name
	 * @return integer
	 */
	public function currentSequenceValue($name)
	{
		return $this->lastInsertId($name);
	}

	/**
	 * function to create auto increment trigger for a table
	 *
	 * @param  string $table
	 * @param  string $column
	 * @param  string $triggerName
	 * @param  string $sequenceName
	 * @return boolean
	 */
	public function createAutoIncrementTrigger($table, $column, $triggerName, $sequenceName)
	{
		if (!$table or !$column or !$triggerName or !$sequenceName) return false;

		return $this->connection->statement("
			create trigger $triggerName
			before insert or update on {$table}
			for each row
				begin
			if inserting and :new.{$column} is null then
				select {$sequenceName}.nextval into :new.{$column} from dual;
			end if;
			end;");
	}

	/**
	 * function to safely drop trigger db object
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function dropTrigger($name)
	{
		if (!$name) return false;

		return $this->connection->statement("
			declare
				e exception;
				pragma exception_init(e,-4080);
			begin
				execute immediate 'drop trigger {$name}';
			exception
			when e then
				null;
			end;");
	}

	/**
	 * get table's primary key
	 *
	 * @param  string $table
	 * @return string
	 */
	public function getPrimaryKey($table)
	{
		if (!$table) return '';

		$data = $this->connection->selectOne("
			SELECT cols.column_name
			FROM all_constraints cons, all_cons_columns cols
			WHERE cols.table_name = upper('{$table}')
				AND cons.constraint_type = 'P'
				AND cons.constraint_name = cols.constraint_name
				AND cons.owner = cols.owner
				AND cols.position = 1
				AND cons.owner = (select user from dual)
			ORDER BY cols.table_name, cols.position
			");

		if (count($data)) return $data->column_name;

		return '';
	}

	/**
	 * function to check if sequence exists
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public function checkSequence($name)
	{
		if (!$name) return false;

		return $this->connection->selectOne("select *
			from all_sequences
			where
				sequence_name=upper('{$name}')
				and sequence_owner=upper(user)
			");
	}

	/**
	 * Create an object name that limits to 30chars
	 *
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
