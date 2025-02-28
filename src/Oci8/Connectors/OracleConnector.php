<?php

namespace Yajra\Oci8\Connectors;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;
use PDO;
use Yajra\Pdo\Oci8;

class OracleConnector extends Connector implements ConnectorInterface
{
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ];

    /**
     * Establish a database connection.
     *
     * @throws \Exception
     */
    public function connect(array $config): PDO
    {
        $tns = ! empty($config['tns']) ? $config['tns'] : $this->getDsn($config);

        $options = $this->getOptions($config);

        if (defined('OCI_CRED_EXT') && Arr::get($options, 'session_mode') === OCI_CRED_EXT) {
            // External connections can only be used with user / and an empty password
            $config['username'] = '/';
            $config['password'] = null;
        }

        return $this->createConnection($tns, $config, $options);
    }

    /**
     * Create a DSN string from a configuration.
     */
    protected function getDsn(array $config): string
    {
        if (! empty($config['tns'])) {
            return $config['tns'];
        }

        // parse configuration
        $config = $this->parseConfig($config);

        // check multiple connections/host, comma delimiter
        $config = $this->checkMultipleHostDsn($config);

        // return generated tns
        return $config['tns'];
    }

    /**
     * Parse configurations.
     */
    protected function parseConfig(array $config): array
    {
        $config = $this->setHost($config);
        $config = $this->setPort($config);
        $config = $this->setProtocol($config);
        $config = $this->setServiceId($config);
        $config = $this->setTNS($config);

        return $this->setCharset($config);
    }

    /**
     * Set host from config.
     */
    protected function setHost(array $config): array
    {
        $config['host'] ??= $config['hostname'];

        return $config;
    }

    /**
     * Set port from config.
     */
    private function setPort(array $config): array
    {
        $config['port'] ??= '1521';

        return $config;
    }

    /**
     * Set protocol from config.
     */
    private function setProtocol(array $config): array
    {
        $config['protocol'] ??= 'TCP';

        return $config;
    }

    /**
     * Set service id from config.
     */
    protected function setServiceId(array $config): array
    {
        $config['service'] = empty($config['service_name'])
            ? 'SID = '.$config['database']
            : 'SERVICE_NAME = '.$config['service_name'];

        return $config;
    }

    /**
     * Set tns from config.
     */
    protected function setTNS(array $config): array
    {
        $config['tns'] = "(DESCRIPTION = (ADDRESS = (PROTOCOL = {$config['protocol']})(HOST = {$config['host']})(PORT = {$config['port']})) (CONNECT_DATA =({$config['service']})))";

        return $config;
    }

    /**
     * Set charset from config.
     */
    protected function setCharset(array $config): array
    {
        if (! isset($config['charset'])) {
            $config['charset'] = 'AL32UTF8';
        }

        return $config;
    }

    /**
     * Set DSN host from config.
     */
    protected function checkMultipleHostDsn(array $config): array
    {
        $host = is_array($config['host']) ? $config['host'] : explode(',', (string) $config['host']);

        $count = count($host);
        if ($count > 1) {
            $address = '';
            for ($i = 0; $i < $count; $i++) {
                $address .= '(ADDRESS = (PROTOCOL = '.$config['protocol'].')(HOST = '.trim((string) $host[$i]).')(PORT = '.$config['port'].'))';
            }

            // backwards compatibility for users dont have this field in their php config
            $loadBalance = $config['load_balance'] ?? 'yes';

            // create a tns with multiple address connection
            $config['tns'] = "(DESCRIPTION = {$address} (LOAD_BALANCE = {$loadBalance}) (FAILOVER = on) (CONNECT_DATA = (SERVER = DEDICATED) ({$config['service']})))";
        }

        return $config;
    }

    /**
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     *
     * @throws \Exception
     */
    public function createConnection($dsn, array $config, array $options): PDO|Oci8
    {
        // add fallback in case driver is not set, will use pdo instead
        if (! in_array($config['driver'], ['oci8', 'pdo-via-oci8', 'oracle'])) {
            return parent::createConnection($dsn, $config, $options);
        }

        $config = $this->setCharset($config);
        $options['charset'] = $config['charset'];

        return parent::createConnection($dsn, $config, $options);
    }

    /**
     * Create a new PDO connection instance.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     */
    protected function createPdoConnection($dsn, $username, #[\SensitiveParameter] $password, $options): Oci8
    {
        return new Oci8($dsn, $username, $password, $options);
    }
}
