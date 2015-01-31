<?php namespace yajra\Oci8;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\OCI8\Driver as DoctrineDriver;
use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use PDO;
use yajra\Oci8\Query\Grammars\OracleGrammar as QueryGrammar;
use yajra\Oci8\Query\OracleBuilder as QueryBuilder;
use yajra\Oci8\Query\Processors\OracleProcessor as Processor;
use yajra\Oci8\Schema\Grammars\OracleGrammar as SchemaGrammar;
use yajra\Oci8\Schema\OracleBuilder as SchemaBuilder;
use yajra\Oci8\Schema\Sequence;
use yajra\Oci8\Schema\Trigger;

class Oci8Connection extends Connection {

	/**
	 * @var string
	 */
	protected $schema;

	/**
	 * @var Sequence
	 */
	protected $sequence;

	/**
	 * @var Trigger
	 */
	protected $trigger;

	public function __construct(PDO $pdo, $database = '', $tablePrefix = '', array $config = [])
	{
		parent::__construct($pdo, $database, $tablePrefix, $config);
		$this->sequence = new Sequence($this);
		$this->trigger = new Trigger($this);
	}

	/**
	 * @param string $schema
	 * @return $this
	 */
	public function setSchema($schema)
	{
		$this->schema = $schema;
		$this->statement("ALTER SESSION SET CURRENT_SCHEMA = {$schema}");

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSchema()
	{
		return $this->schema;
	}

	/**
	 * @return Sequence
	 */
	public function getSequence()
	{
		return $this->sequence;
	}

	/**
	 * @param Sequence $sequence
	 * @return \yajra\Oci8\Schema\Sequence
	 */
	public function setSequence(Sequence $sequence)
	{
		return $this->sequence = $sequence;
	}

	/**
	 * @return Trigger
	 */
	public function getTrigger()
	{
		return $this->trigger;
	}

	/**
	 * @param Trigger $trigger
	 * @return \yajra\Oci8\Schema\Trigger
	 */
	public function setTrigger(Trigger $trigger)
	{
		return $this->trigger = $trigger;
	}

	/**
	 * @inheritdoc
	 */
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new QueryGrammar);
	}

	/**
	 * @inheritdoc
	 */
	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new SchemaGrammar);
	}

	/**
	 * @return Processor
	 */
	protected function getDefaultPostProcessor()
	{
		return new Processor;
	}

	/**
	 * @return SchemaBuilder
	 */
	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar))
		{
			$this->useDefaultSchemaGrammar();
		}

		return new SchemaBuilder($this);
	}

	/**
	 * @inheritdoc
	 */
	public function table($table)
	{
		$processor = $this->getPostProcessor();

		$query = new QueryBuilder($this, $this->getQueryGrammar(), $processor);

		return $query->from($table);
	}


	/**
	 * @param string $format
	 * @return $this
	 */
	public function setSessionVars($sessionVars = [])
	{
		$vars = [];
		foreach ($sessionVars as $option => $value) {
			$vars[] = $option." = ".$value;
		}
		$sql = "ALTER SESSION SET ".implode(" ", $vars);

		$this->statement($sql);

		return $this;
	}

	/**
	 * @param string $format
	 * @return $this
	 */
	public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
	{
		$this->statement("alter session set NLS_DATE_FORMAT = '$format'");
		$this->statement("alter session set NLS_TIMESTAMP_FORMAT = '$format'");

		return $this;
	}

	/**
	 * @return DoctrineConnection
	 */
	public function getDoctrineConnection()
	{
		$driver = $this->getDoctrineDriver();

		$data = ['pdo' => $this->pdo, 'user' => $this->getConfig('database')];

		return new DoctrineConnection($data, $driver);
	}

	/**
	 * @return DoctrineDriver
	 */
	protected function getDoctrineDriver()
	{
		return new DoctrineDriver;
	}

	/**
	 * @param Grammar $grammar
	 * @return Grammar
	 */
	public function withTablePrefix(Grammar $grammar)
	{
		return parent::withTablePrefix($grammar);
	}

}
