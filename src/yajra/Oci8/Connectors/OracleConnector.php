<?php namespace yajra\Oci8\Connectors;

use Illuminate\Database\Connectors;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class OracleConnector extends Connector implements ConnectorInterface
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
     * @param  string  $tns
     * @param  array   $config
     * @param  array   $options
     * @return \PDO
     */
    public function createConnection($tns, array $config, array $options)
    {
        // add fallback in case driver is not set, will use pdo instead
        if ( !in_array($config['driver'], array('oci8', 'pdo-via-oci8', 'oracle')) ) {
            return parent::createConnection($tns, $config, $options);
        }

        // check charset
        if (!isset($config['charset'])) {
            $config['charset'] = '';
        }

        $options['charset'] = $config['charset'];
        return new \yajra\Pdo\Oci8($tns, $config['username'], $config['password'], $options);
    }

    /**
     * Establish a database connection.
     *
     * @return \PDO
     */
    public function connect(array $config)
    {
        $tns = $this->getDsn($config);

        $options = $this->getOptions($config);

        $connection = $this->createConnection($tns, $config, $options);

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
        // check port
        if (!isset($config['port'])) {
            $config['port'] = '1521';
        }

        // check host config
        if (isset($config['hostname']) and !isset($config['host']))
            $config['host'] = $config['hostname'];

        // check tns
        if (empty($config['tns'])) {
             //Create a description to locate the database to connect to
            $config['tns'] = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = {$config['host']})(PORT = {$config['port']})) (CONNECT_DATA =(SID = {$config['database']})))";
        }

        // check multiple connections/host, comma delimiter
        if( isset($config['host']) ){
            $host = explode(',', $config['host']);
            if (count($host) > 1) {
                $address  = "";

                for($i = 0;$i < count($host); $i++){
                   $address .= '(ADDRESS = (PROTOCOL = TCP)(HOST = '.trim($host[$i]).')(PORT = '.$config['port'].'))';
                }

                // create a tns with multiple address connection
                $config['tns'] = "(DESCRIPTION = {$address} (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = {$config['database']})))";
            }
        }

        // return generated tns
        return $config['tns'];
    }

}
