<?php namespace Jfelder\OracleDB\OCI_PDO;

use \PDOException;

class OCIException extends PDOException
{

	/**
	 * The SQL for the query.
	 *
	 * @var string
	 */
	protected $sql;

	/**
	 * Create a new query exception instance.
	 *
	 * @param  string  $sql
	 * @param  array  $bindings
	 * @param  array $previous
	 * @return void
	 */
	public function __construct($previous)
	{
		$this->previous = $previous;
                $this->code = $previous["code"];
                $this->message = $previous["message"];
                $this->sql = $previous["sqltext"];
                

	}

	/**
	 * Get the SQL for the query.
	 *
	 * @return string
	 */
	public function getSql()
	{
		return $this->sql;
	}

}