<?php

use Mockery as m;
use Yajra\Oci8\Connectors\OracleConnector;

class OracleConnectorStub extends OracleConnector
{
    public function createConnection($tns, array $config, array $options)
    {
        return new Oci8Stub($tns, $config['username'], $config['password'], $config['options']);
    }
}
