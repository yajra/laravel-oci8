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
     * @inheritdoc
     */
 	protected function getDefaultPostProcessor()
 	{
 		return new Processor;
 	}

    /**
     * @inheritdoc
     */
	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar)) { $this->useDefaultSchemaGrammar(); }

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
     * @inheritdoc
     */
	public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
	{
		self::statement("alter session set NLS_DATE_FORMAT = '$format'");
		self::statement("alter session set NLS_TIMESTAMP_FORMAT = '$format'");
	}

    /**
     * @inheritdoc
     */
	public function getDoctrineConnection()
	{
		$driver = $this->getDoctrineDriver();

		$data = array('pdo' => $this->pdo, 'user' => $this->getConfig('database'));

		return new DoctrineConnection($data, $driver);
	}

    /**
     * @inheritdoc
     */
	protected function getDoctrineDriver()
	{
		return new DoctrineDriver;
	}

}
