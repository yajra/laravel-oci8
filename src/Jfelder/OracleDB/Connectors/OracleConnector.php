<?php namespace Jfelder\OracleDB\Connectors;

use Illuminate\Database\Connectors;

class OracleConnector extends \Illuminate\Database\Connectors\Connector implements \Illuminate\Database\Connectors\ConnectorInterface 
{

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
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return PDO
     */
    public function createConnection($dsn, array $config, array $options)
    {
        if($config['driver'] == 'oci') {
            return new \Jfelder\OracleDB\OCI_PDO\OCI($dsn, $config['username'], $config['password'], $options, $config['charset']);
        } else {
            return parent::createConnection($dsn, $config, $options);                    
        }
    }

    /**
     * Establish a database connection.
     *
     * @param  array  $options
     * @return PDO
     */
    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);

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
        if (empty($config['tns'])) 
            $config['tns'] = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = {$config['host']})(PORT = {$config['port']}))(CONNECT_DATA =(SID = {$config['database']})))";

        $dsn = $config['tns'];

        if($config['driver'] == 'pdo') 
            $dsn = "oci:dbname=".$dsn.(empty($config['charset']) ? "" : ";charset=".$config['charset']);

        return $dsn; 
    }        
}