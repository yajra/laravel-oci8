<?php

if(!class_exists('TestOCIStub')) {
    class TestOCIStub extends Jfelder\OracleDB\OCI_PDO\OCI {
            public function __construct() { $this->conn = true; }
            public function __destruct() {}
    }
}

if(!class_exists('TestOCIStatementStub')) {
    class TestOCIStatementStub extends Jfelder\OracleDB\OCI_PDO\OCIStatement {
            public function __construct($stmt, $conn, $options) { $this->stmt = $stmt; $this->conn = $conn; $this->attributes = $options; }
            public function __destruct() {}
    }
}

if(!class_exists('ProcessorTestOCIStub')) {
    class ProcessorTestOCIStub extends Jfelder\OracleDB\OCI_PDO\OCI {
            public function __construct() {}
            public function __destruct() {}
            public function prepare($statement, $driver_options = array()) {}
    }
}

if(!class_exists('ProcessorTestOCIStatementStub')) {
    class ProcessorTestOCIStatementStub extends Jfelder\OracleDB\OCI_PDO\OCIStatement {
            public function __construct() {}
            public function __destruct() {}
            public function bindValue($parameter, $value, $data_type = 'PDO::PARAM_STR') {}
            public function bindParam($parameter, &$variable, $data_type = 'PDO::PARAM_STR', $length = null, $driver_options = null) {}
            public function execute($input_parameters = null) {}
    }
}
