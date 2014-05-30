<?php
$ConfigReturnValue = false;

if(!class_exists('ProcessorTestPDOStub')) {
    class ProcessorTestPDOStub extends PDO {
            public function __construct() {}
            public function lastInsertId($sequence = null) {}
    }
}
if(!class_exists('Config')) {
    class Config {
		public static function get($value) { global $ConfigReturnValue; return $ConfigReturnValue; }
    }
}