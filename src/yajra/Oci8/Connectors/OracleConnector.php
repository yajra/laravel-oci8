<?php namespace yajra\Oci8\Connectors;

use Illuminate\Database\Connectors;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use yajra\Pdo\Oci8;
use PDO;

class OracleConnector extends Connector implements ConnectorInterface
{

    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = array(
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        );

    /**
     * Create a new PDO connection.
     *
     * @param  string  $tns
     * @param  array   $config
     * @param  array   $options
     * @return PDO
     */
    public function createConnection($tns, array $config, array $options)
    {
        // add fallback in case driver is not set, will use pdo instead
        if ( !in_array($config['driver'], array('oci8', 'pdo-via-oci8', 'oracle')) ) {
            return parent::createConnection($tns, $config, $options);
        }

        // check charset
        if (!isset($config['charset'])) {
            $config['charset'] = 'AL32UTF8';
        }

        $options['charset'] = $config['charset'];
        return new Oci8($tns, $config['username'], $config['password'], $options);
    }

    /**
     * Establish a database connection.
     *
     * @return PDO
     */
    public function connect(array $config)
    {
        $tns = $this->getDsn($config);

        $options = $this->getOptions($config);

        $connection = $this->createConnection($tns, $config, $options);

        // Like Postgres, Oracle allows the concept of "schema"
        if (isset($config['schema']))
        {
            $schema = $config['schema'];
            $connection->prepare("ALTER SESSION SET CURRENT_SCHEMA = {$schema}")->execute();
        }

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
        if (!isset($config['port'])) $config['port'] = '1521';

        // check protocol
        if (!isset($config['protocol']))
        {
            $config['protocol'] = 'TCP';
        }
        else
        {
            $config['protocol'] = strtoupper($config['protocol']);
        }

        // check protocol
        if (!isset($config['protocol'])) {
            $config['protocol'] = 'TCP';
        } else {
            $config['protocol'] = strtoupper($config['protocol']);
        }

        // check host config
        if (isset($config['hostname']) and !isset($config['host'])) $config['host'] = $config['hostname'];

        // check tns
        if ( empty($config['tns']) )
        {
            // Create a description to locate the database to connect to
            $config['tns'] = "(DESCRIPTION = (ADDRESS = (PROTOCOL = {$config['protocol']})(HOST = {$config['host']})(PORT = {$config['port']})) (CONNECT_DATA =(SERVICE_NAME = {$config['database']})))";
            // check if we will use Service Name
            if( empty($config['service_name']) )
            {
                $service_param = 'SID = '.$config['database'];
            }
            else
            {
                $service_param = 'SERVICE_NAME = '.$config['service_name'];
            }

            $config['tns'] = "(DESCRIPTION = (ADDRESS = (PROTOCOL = {$config['protocol']})(HOST = {$config['host']})(PORT = {$config['port']})) (CONNECT_DATA =($service_param)))";
        }

        // check multiple connections/host, comma delimiter
        if (isset($config['host']))
        {
            $host = explode(',', $config['host']);
            if (count($host) > 1)
            {
                $address  = "";
                for($i = 0;$i < count($host); $i++)
                {
                   $address .= '(ADDRESS = (PROTOCOL = '.$config["protocol"].')(HOST = '.trim($host[$i]).')(PORT = '.$config['port'].'))';
                }

                // create a tns with multiple address connection
                $config['tns'] = "(DESCRIPTION = {$address} (LOAD_BALANCE = yes) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) (SERVICE_NAME = {$config['database']})))";
            }
        }

        // return generated tns
        return $config['tns'];
    }

}
