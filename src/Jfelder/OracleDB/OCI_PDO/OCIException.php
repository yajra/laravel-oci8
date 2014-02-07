<?php namespace Jfelder\OracleDB\OCI_PDO;

use \PDOException;

class OCIException extends PDOException
{

    /**
     * Create a new query exception instance.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  array $previous
     * @return void
     */
    public function __construct($e)
    {
        $this->errorInfo = $e;
        $this->code = $e[1];
        $this->message = "SQLSTATE[$e[0]] " . $e[2];
    }

}