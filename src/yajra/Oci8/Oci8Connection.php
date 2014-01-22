<?php namespace yajra\Oci8;

use Illuminate\Database\Connection;

class Oci8Connection extends Connection {

	// make PDO object public to support raw queries
	// using pdo object
	public $pdo;

	/**
	 * Get the default query grammar instance.
	 *
	 * @return Illuminate\Database\Query\Grammars\Grammars\Grammar
	 */
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\OracleGrammar);
	}

	/**
	 * Get the default schema grammar instance.
	 *
	 * @return Illuminate\Database\Schema\Grammars\Grammar
	 */
	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\OracleGrammar);
	}

	/**
	 * Get the schema grammar used by the connection.
	 *
	 * @return \Illuminate\Database\Query\Grammars\Grammar
	 */
	public function getSchemaGrammar()
	{
		return $this->getDefaultSchemaGrammar();
	}

	/**
	 * Get a schema builder instance for the connection.
	 *
	 * @return \Illuminate\Database\Schema\Builder
	 */
	public function getSchemaBuilder()
	{
		return new Schema\OracleBuilder($this);
	}

	/**
	 * function to set oracle's current session date format
	 * @param string $format
	 */
	public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
	{
		self::statement("alter session set nls_date_format = '$format'");
	}

	/**
	 * function to create oracle sequence
	 * @param  strine $name
	 * @return boolean
	 */
	public function createSequence($name)
	{
		if (!$name) {
			return false;
		}
		return self::statement('CREATE SEQUENCE '. $name);
	}

	/**
	 * function to drop oracle sequence
	 * @param  strine $name
	 * @return boolean
	 */
	public function dropSequence($name)
	{
		if (!$name) {
			return false;
		}
		return self::statement('DROP SEQUENCE '. $name);
	}

	/**
	 * function to get oracle sequence last inserted id
	 * @param  strine $name
	 * @return integer
	 */
	public function lastInsertId($name)
	{
		if (!$name) {
			return 0;
		}
		$data = self::select("SELECT $name.CURRVAL as id FROM DUAL");
		return $data[0]->id;
	}

	/**
	 * get sequence next value
	 * @param  string $name
	 * @return integer
	 */
	public function nextSequenceValue($name) {
		if (!$name) {
			return 0;
		}
		$data = self::select("SELECT $name.NEXTVAL as id FROM DUAL");
		return $data[0]->id;
	}

	/**
	 * same function as lastInsertId. added for clarity with oracle sql statement.
	 * @param  string $name
	 * @return integer
	 */
	public function currentSequenceValue($name)
	{
		return $this->lastInsertId($name);
	}

	/**
	 * function to create auto increment trigger for a table
	 * @param  string $table
	 * @param  string $column
	 * @return boolean
	 */
	public function createAutoIncrementTrigger($table, $column)
	{
		if (!$table or !$column) {
			return 0;
		}
		return self::statement("
			create trigger {$table}_{$column}_trg
			before insert or update on {$table}
			for each row
				begin
			if inserting and :new.{$column} is null then
				select {$table}_{$column}_seq.nextval into :new.{$column} from dual;
			end if;
			end;");
	}

}