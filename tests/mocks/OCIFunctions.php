<?php
namespace {
    $OCITransactionStatus = true;
    $OCIStatementStatus = true;
    $OCIConnectionStatus = true;
    $OCIExecuteStatus = true;
    $OCIFetchStatus = true;
    $OCIBindChangeStatus = false;
}

namespace Jfelder\OracleDB\OCI_PDO {  
    // Generic
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_error")) {    
        function oci_error($a="") { 
            return array('code'=>0,'message'=>'', 'sqltext'=>''); 
        } 
    }

    // OCI specific
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_connect")) {    
        function oci_connect($a="") { 
            global $OCITransactionStatus; 
            return $OCITransactionStatus ? 'oci8' : false;  
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_pconnect")) {    
        function oci_pconnect($a="") { 
            global $OCITransactionStatus; 
            return $OCITransactionStatus ? 'oci8' : false;  
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_close")) {    
        function oci_close($a="") { 
            global $OCITransactionStatus; 
            $OCITransactionStatus = false; 
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_commit")) {    
        function oci_commit($a="") { 
            global $OCITransactionStatus; 
            return $OCITransactionStatus;  
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_rollback")) {    
        function oci_rollback($a="") { 
            global $OCITransactionStatus; 
            return $OCITransactionStatus;  
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_parse")) {    
        function oci_parse($a="", $b="") { 
            global $OCITransactionStatus; 
            return $OCITransactionStatus ? 'oci8 statement' : false;  
        } 
    }

    // OCI Statement specific
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\get_resource_type")) {    
        function get_resource_type($a="") { 
            global $OCIStatementStatus; 
            return $OCIStatementStatus ? $a : 'invalid'; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_bind_by_name")) {    
        function oci_bind_by_name($a="", $b="", &$c, $d="", $e="") { 
            global $OCIStatementStatus, $OCIBindChangeStatus; 
            if($OCIBindChangeStatus) $c = 'oci_bind_by_name'; 
            return $OCIStatementStatus; 
        } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_num_fields")) {    
        function oci_num_fields($a="") { return 1; } 
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_free_statement")) {    
        function oci_free_statement($a="") { 
            global $OCIStatementStatus; 
            $OCIStatementStatus = false; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_execute")) {    
        function oci_execute($a="", $b="") { 
            global $OCIExecuteStatus; 
            return $OCIExecuteStatus; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_fetch_assoc")) {    
        function oci_fetch_assoc($a="") { 
            global $OCIFetchStatus; 
            return $OCIFetchStatus ? array('FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com') : false; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_fetch_row")) {    
        function oci_fetch_row($a="") { 
            global $OCIFetchStatus; 
            return $OCIFetchStatus ? array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com') : false; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_fetch_array")) {    
        function oci_fetch_array($a="") { 
            global $OCIFetchStatus; 
            return $OCIFetchStatus ? array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com', 'FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com') : false; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_fetch_all")) {    
        function oci_fetch_all($a="", &$b) { 
            global $OCIFetchStatus; 
            $b = array(array('FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com')); 
            return $OCIFetchStatus; 
        }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_field_type")) {    
        function oci_field_type($a, $b) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_field_type_raw")) {    
        function oci_field_type_raw($a, $b) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_field_name")) {    
        function oci_field_name($a, $b) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_field_size")) {    
        function oci_field_size($a, $b) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_field_precision")) {    
        function oci_field_precision($a, $b) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_num_rows")) {    
        function oci_num_rows($a) { return 1; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_client_version")) {    
        function oci_client_version() { return "Test Return"; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_free_descriptor")) {    
        function oci_free_descriptor($a) { return $a; }
    }
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_internal_debug")) {    
        function oci_internal_debug($a) { global $OCITransactionStatus; $OCITransactionStatus = $a; }
    }
}