<?php

use Yajra\Pdo\Oci8;

class Oci8Stub extends Oci8
{
    public function __construct($dsn, $username, $password, array $options = [])
    {
        return true;
    }
}
