<?php namespace Jfelder\OracleDB\Connectors;

use Illuminate\Database\Connectors;

class OracleConnector extends \Illuminate\Database\Connectors\Connector implements \Illuminate\Database\Connectors\ConnectorInterface {

    	/**
	 * The default PDO connection options.
	 *
	 * @var array
	 */
	protected $options = array(
			\PDO::ATTR_CASE => \PDO::CASE_LOWER,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
	);

	/**
	 * Establish a database connection.
	 *
	 * @param  array  $options
	 * @return PDO
	 */
	public function connect(array $config)
	{
		$dsn = $this->getDsn($config);

		// We need to grab the PDO options that should be used while making the brand
		// new connection instance. The PDO options control various aspects of the
		// connection's behavior, and some might be specified by the developers.
		$options = $this->getOptions($config);

		$connection = $this->createConnection($dsn, $config, $options);
                
		return $connection;
	}

	/**
	 * Create a DSN string from a configuration.
	 *
	 * @param  array   $config
	 * @return string
	 */
	protected function getDsn(array $config)
	{
		// First we will create the basic DSN setup as well as the port if it is in
		// in the configuration options. This will give us the basic DSN we will
		// need to establish the PDO connections and return them back for use.
		extract($config);

		if (isset($config['port'])) 
                {
                    $port = ":{$config['port']}";
		} 
                else 
                {
                    $port = "";
		}

                // If no host, the db name must be defined in tnsnames.ora 
		if (!empty($config['host'])) 
                {
                    $dsn = "oci:dbname={$config['host']}{$port}/{$config['database']}";
		} 
		elseif (!empty($config['tns'])) 
                {
                    $dsn = "oci:dbname={$config['tns']}";
		} 
                else 
                {
                    $dsn = "oci:dbname={$config['database']}";
		}

		// If a character set has been specified, include it
                if (isset($config['charset'])) 
                {
                    $dsn .= ";charset=".$config['charset'];
                }

                return $dsn;
	}
        
}