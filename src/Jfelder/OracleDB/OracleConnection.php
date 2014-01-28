<?php namespace Jfelder\OracleDB;

use Illuminate\Database\Connection;

class OracleConnection extends Connection {

	/**
	 * Get the default query grammar instance.
	 *
	 * @return Jfelder\OracleDB\Query\Grammars\OracleGrammar
	 */
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\OracleGrammar);
	}

	/**
	 * Get the default schema grammar instance.
	 *
	 * @return Jfelder\OracleDB\Schema\Grammars\OracleGrammar
	 */
	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\OracleGrammar);
	}

	/**
	 * Get the default post processor instance.
	 *
	 * @return Jfelder\OracleDB\Query\Processors\OracleProcessor
	 */
	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\OracleProcessor;
	}

}