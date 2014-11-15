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
     * @return QueryGrammar
     */
    protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new QueryGrammar);
	}

    /**
     * @return QueryGrammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * @return SchemaGrammar
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
     */
    public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
	{
		self::statement("alter session set NLS_DATE_FORMAT = '$format'");
		self::statement("alter session set NLS_TIMESTAMP_FORMAT = '$format'");
	}

    /**
     * @return DoctrineConnection
     */
    public function getDoctrineConnection()
	{
		$driver = $this->getDoctrineDriver();

		$data = array('pdo' => $this->pdo, 'user' => $this->getConfig('database'));

		return new DoctrineConnection($data, $driver);
	}

    /**
     * @return DoctrineDriver
     */
    protected function getDoctrineDriver()
	{
		return new DoctrineDriver;
	}

}
