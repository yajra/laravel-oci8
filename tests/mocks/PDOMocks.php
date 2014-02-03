<?php

if(!class_exists('ProcessorTestPDOStub')) {
    class ProcessorTestPDOStub extends PDO {
            public function __construct() {}
            public function lastInsertId($sequence = null) {}
    }
}