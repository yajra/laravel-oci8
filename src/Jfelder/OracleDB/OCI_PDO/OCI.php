<?php namespace Jfelder\OracleDB\OCI_PDO;

class OCI extends \PDO
{

    /*
     * Database connection 
     * 
     * @var oci8
     */
    private $conn;

    /*
     * Database connection attributes
     * 
     * @var array
     */
    protected $attributes = array(\PDO::ATTR_AUTOCOMMIT => 1,
        \PDO::ATTR_ERRMODE => 0,
        \PDO::ATTR_CASE => 0,
        \PDO::ATTR_ORACLE_NULLS => 0,
        );

    /*
     * Tracks if currently in a transaction 
     * 
     * @var bool
     */
    protected $transaction = false;
    
    /*
     * Mode for executing on Database Connection 
     * 
     * @var int
     */
    protected $mode = \OCI_COMMIT_ON_SUCCESS;

    public function __construct ( $dsn, $username = null, $password = null, $driver_options = array(), $charset = '' ) 
    {
        $this->attributes = $driver_options + $this->attributes;

        if($this->getAttribute(\PDO::ATTR_PERSISTENT)) {
            $this->conn = oci_pconnect($username, $password, $dsn, $charset);
        } else {
            $this->conn = oci_connect($username, $password, $dsn, $charset);
        }
        
        //Check if connection was successful
        if (!$this->conn) {
            $e = oci_error();
            throw new OCIException($e);
        }
    }

    public function __destruct() 
    {
        oci_close($this->conn);
    }

    public function beginTransaction()
    {
        if($this->inTransaction()) {
            throw new OCIException(array('code'=>0, 'message'=>"Already in a transaction",'sqltext'=>''));
        }
        
        $this->transaction = $this->setExecuteMode(\OCI_NO_AUTO_COMMIT);
        return true;
    }
    
    public function commit ()
    {
        if($this->inTransaction()) {
            //commit trans
            $r = oci_commit($this->conn);
            if (!$r) {
                $e = oci_error($this->conn);
                throw new OCIException($e);
            }
            $this->transaction = ! $this->flipExecuteMode();
            
            return true;
        }
        
        return false;
    }
    
    public function errorCode ()
    {}
    
    public function errorInfo ()
    {}
    
    public function exec ($statement)
    {}
    
    public function getAttribute ($attribute)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }
        return null;
    }
    
    public static function getAvailableDrivers ()
    {}
    
    public function inTransaction ()
    {
        return $this->transaction;
    }
    
    public function lastInsertId ($name = null)
    {
        return null;
    }
    
    public function prepare ($statement, $driver_options = array())
    {
        $tokens = explode("?" , $statement);
        
        $count = count($tokens) - 1;
        if($count) {
            $statement = "";
            for($i=0;$i<$count;$i++) {
                $statement .= trim($tokens[$i])." :{$i} ";
            }
            $statement .= trim($tokens[$i]);
        }

        $stmt = oci_parse($this->conn, $statement);

        return new OCIStatement($stmt, $this, $driver_options);
    }

    public function query ($statement)
    {}

    public function quote ($string, $parameter_type = \PDO::PARAM_STR )
    {}

    public function rollBack()
    {
        if($this->inTransaction()) {
            //rollback trans
            $r = oci_rollback($this->conn);
            if (!$r) {
                $e = oci_error($this->conn);
                throw new OCIException($e);
            }
            $this->transaction = ! $this->flipExecuteMode();
            
            return true;
        } 
        
        return false;
    }

    public function setAttribute ($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
        return true;
    }
    
    public function getExecuteMode() 
    {
        return $this->mode;
    }

    public function flipExecuteMode() 
    {
        $this->setExecuteMode($this->getExecuteMode() == \OCI_COMMIT_ON_SUCCESS ? \OCI_NO_AUTO_COMMIT : \OCI_COMMIT_ON_SUCCESS);
        return true;
    }

    public function setExecuteMode($mode) 
    {
        if($mode === \OCI_COMMIT_ON_SUCCESS || $mode === \OCI_NO_AUTO_COMMIT) {
            $this->mode = $mode;
            return true;
        }

        throw new OCIException(array('code'=>0,'message'=>"Invalid Commit Mode specified: {$mode}", 'sqltext'=>""));
    }
}