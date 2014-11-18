<?php

use yajra\Oci8\Connectors\OracleConnector;
use Mockery as m;

class OracleConnectorStub extends OracleConnector {

    public function createConnection($tns, array $config, array $options)
    {
        return new Oci8Stub($tns, $config['username'], $config['password'], $config['options']);
    }

}
