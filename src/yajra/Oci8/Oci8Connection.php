<?php namespace yajra\Oci8;

use Illuminate\Database\Connection;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\OCI8\Driver as DoctrineDriver;
use yajra\Oci8\Query\Grammars\OracleGrammar as QueryGrammar;
use yajra\Oci8\Schema\Grammars\OracleGrammar as SchemaGrammar;
use yajra\Oci8\Query\Processors\OracleProcessor as Processor;
use yajra\Oci8\Schema\OracleBuilder as SchemaBuilder;
use yajra\Oci8\Query\OracleBuilder as QueryBuilder;

class Oci8Connection extends Connection {

	/**
	 * Get the default query grammar instance.
	 *
	 * @return \yajra\Oci8\Query\Grammars\OracleGrammar
	 */
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new QueryGrammar);
	}

	/**
	 * Get the default schema grammar instance.
	 *
	 * @return \yajra\Oci8\Schema\Grammars\OracleGrammar
	 */
	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new SchemaGrammar);
	}

	/**
 	 * Get the default post processor instance.
 	 *
 	 * @return \yajra\Oci8\Query\Processors\OracleProcessor
 	 */
 	protected function getDefaultPostProcessor()
 	{
 		return new Processor;
 	}

	/**
	 * Get a schema builder instance for the connection.
	 *
	 * @return \yajra\Oci8\Schema\OracleBuilder
	 */
	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar)) { $this->useDefaultSchemaGrammar(); }

		return new SchemaBuilder($this);
	}

	/**
	 * Begin a fluent query against a database table.
	 *
	 * @param  string  $table
	 * @return \yajra\Oci8\Query\OracleBuilder
	 */
	public function table($table)
	{
		$processor = $this->getPostProcessor();

		$query = new QueryBuilder($this, $this->getQueryGrammar(), $processor);

		return $query->from($table);
	}

	/**
	 * function to set oracle's current session date format
	 *
	 * @param string $format
	 */
	public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
	{
		self::statement("alter session set NLS_DATE_FORMAT = '$format'");
		self::statement("alter session set NLS_TIMESTAMP_FORMAT = '$format'");
	}

	/**
	 * Get the Doctrine DBAL database connection instance.
	 *
	 * @return \Doctrine\DBAL\Connection
	 */
	public function getDoctrineConnection()
	{
		$driver = $this->getDoctrineDriver();

		$data = array('pdo' => $this->pdo, 'user' => $this->getConfig('database'));

		return new DoctrineConnection($data, $driver);
	}

	/**
	 * Get the Doctrine DBAL driver.
	 *
	 * @return \Doctrine\DBAL\Driver\OCI8\Driver
	 */
	protected function getDoctrineDriver()
	{
		return new DoctrineDriver;
	}

}
