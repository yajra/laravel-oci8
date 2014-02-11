<?php
/**
 * PDO userspace driver proxying calls to PHP OCI8 driver
 *
 * @category Database
 * @package yajra/laravel-oci8
 * @author Arjay Angeles
 * @copyright Copyright (c) 2013 Arjay Angeles (http://github.com/yajra)
 * @license MIT
 */
namespace yajra\Oci8\Connectors;

/**
 * Oci8 class to mimic the interface of the PDO class
 *
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8 extends \PDO
{

    /**
     * Database handler
     *
     * @var resource
     */
    public $_dbh;

    /**
     * Driver options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Whether currently in a transaction
     *
     * @var bool
     */
    protected $_isTransaction = false;

    /**
     * insert query statement table variable
     *
     * @var string
     */
    protected $_table;

    /**
     * Constructor
     *
     * @param string $dsn
     * @param string $username
     * @param string $passwd
     * @param array $options
     * @return void
     */
    public function __construct($dsn, $username = null, $password = null, array $options = array())
    {
        //Parse the DSN
        $parsedDsn = self::parseDsn($dsn, array('charset'));

        //Get SID name
        $sidString = (isset($parsedDsn['sid'])) ? '(SID = '.$parsedDsn['sid'].')' : '';

        if( strpos($parsedDsn['hostname'],",") !== FALSE ){

            $hostname = explode(',',$parsedDsn['hostname']);
            $count    = count($hostname);
            $address  = "";

            for($i = 0;$i < $count; $i++){
               $address .= '(ADDRESS = (PROTOCOL = TCP)(HOST = '.$hostname[$i].')(PORT = '.$parsedDsn['port'].'))';
            }

             //Create a description to locate the database to connect to
            $description = '(DESCRIPTION =
                '.$address.'
                (LOAD_BALANCE = yes)
                (FAILOVER = on)
                (CONNECT_DATA =
                        '.$sidString.'
                        (SERVER = DEDICATED)
                        (SERVICE_NAME = '.$parsedDsn['dbname'].')
                )
            )';

        } else {

             //Create a description to locate the database to connect to
             $description = '(DESCRIPTION =
                    (ADDRESS_LIST =
                        (ADDRESS = (PROTOCOL = TCP)(HOST = '.$parsedDsn['hostname'].')
                        (PORT = '.$parsedDsn['port'].'))
                    )
                    (CONNECT_DATA =
                            '.$sidString.'
                            (SERVICE_NAME = '.$parsedDsn['dbname'].')
                    )
                )';

        }

        //Attempt a connection
        if (isset($options[\PDO::ATTR_PERSISTENT])
            && $options[\PDO::ATTR_PERSISTENT]) {

            $this->_dbh = @oci_pconnect(
                $username,
                $password,
                $description,
                $parsedDsn['charset']);

        } else {

            $this->_dbh = @oci_connect(
                $username,
                $password,
                $description,
                $parsedDsn['charset']);

        }

        //Check if connection was successful
        if (!$this->_dbh) {
            $e = oci_error();
            throw new \PDOException($e['message']);
        }

        //Save the options
        $this->_options = $options;

    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $statement
     * @param array $options
     * @return Pdo_Oci8_Statement
     */
    public function prepare($statement, $options = null)
    {

        // Get instance options
        if($options == null) $options = $this->_options;
        //Replace ? with a pseudo named parameter
        $newStatement = null;
        $parameter = 0;
        while($newStatement !== $statement)
        {
            if($newStatement !== null)
            {
                $statement = $newStatement;
            }
            $newStatement = preg_replace('/\?/', ':autoparam'.$parameter, $statement, 1);
            $parameter++;
        }
        $statement = $newStatement;

        // check if statement is insert function
        if (strpos(strtolower($statement), 'insert into')!==false) {
            preg_match('/insert into (.*?) /', strtolower($statement), $matches);
            // store insert into table name
            $this->_table = $matches[1];
        }

        //Prepare the statement
        $sth = @oci_parse($this->_dbh, $statement);

        if (!$sth) {
            $e = oci_error($this->_dbh);
            throw new \PDOException($e['message']);
        }

        if (!is_array($options)) {
            $options = array();
        }

        return new Oci8\Statement($sth, $this, $options);
    }

    /**
     * Begins a transaction (turns off autocommit mode)
     *
     * @return void
     */
    public function beginTransaction()
    {
        if ($this->isTransaction()) {
            throw new \PDOException('There is already an active transaction');
        }

        $this->_isTransaction = true;
        return true;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->_isTransaction;
    }

    /**
     * Commits all statements issued during a transaction and ends the transaction
     *
     * @return bool
     */
    public function commit()
    {
        if (!$this->isTransaction()) {
            throw new \PDOException('There is no active transaction');
        }

        if (oci_commit($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back a transaction
     *
     * @return bool
     */
    public function rollBack()
    {
        if (!$this->isTransaction()) {
            throw new \PDOException('There is no active transaction');
        }

        if (oci_rollback($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Sets an attribute on the database handle
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($attribute, $value)
    {
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Executes an SQL statement and returns the number of affected rows
     *
     * @param string $query
     * @return int The number of rows affected
     */
    public function exec($query)
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Executes an SQL statement, returning the results as a Pdo_Oci8_Statement
     *
     * @param string $query
     * @param int|null $fetchType
     * @param mixed|null $typeArg
     * @param array|null $ctorArgs
     * @return Pdo_Oci8_Statement
     * @todo Implement support for $fetchType, $typeArg, and $ctorArgs.
     */
    public function query($query,
                          $fetchType = null,
                          $typeArg = null,
                          array $ctorArgs = array())
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Issues a PHP warning, just as with the PDO_OCI driver
     *
     * Oracle does not support the last inserted ID functionality like MySQL.
     * You must implement this yourself by returning the sequence ID from a
     * stored procedure, for example.
     *
     * @param string $name Sequence name; no use in this context
     * @return void
     */
    public function lastInsertId($name = null)
    {
        $sequence = $this->_table . "_" . $name . "_seq";
        if (!$this->checkSequence($sequence))
            return 0;

        $stmt = $this->query("select {$sequence}.currval from dual");
        $id = $stmt->fetch();
        return $id;
    }

    /**
     * Returns the error code associated with the last operation
     *
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string
     */
    public function errorCode()
    {
        $error = $this->errorInfo();
        return $error[0];
    }

    /**
     * Returns extended error information for the last operation on the database
     *
     * @return array
     */
    public function errorInfo()
    {
        $e = oci_error($this->_dbh);

        if (is_array($e)) {
            return array(
                'HY000',
                $e['code'],
                $e['message']
            );
        }

        return array('00000', null, null);
    }

    /**
     * Retrieve a database connection attribute
     *
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        if (isset($this->_options[$attribute])) {
            return $this->_options[$attribute];
        }
        return null;
    }

    /**
     * Special non PDO function used to start cursors in the database
     * Remember to call oci_free_statement() on your cursor
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function getNewCursor()
    {
        return oci_new_cursor($this->_dbh);
    }

    /**
     * Special non PDO function used to start descriptor in the database
     * Remember to call oci_free_statement() on your cursor
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function getNewDescriptor($type = OCI_D_LOB)
    {
        return oci_new_descriptor($this->_dbh, $type);
    }

    /**
     * Special non PDO function used to close an open cursor in the database
     *
     * @param mixed $cursor Description.
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function closeCursor($cursor)
    {
        return oci_free_statement($cursor);
    }

    /**
     * Quotes a string for use in a query
     *
     * @param string $string
     * @param int $paramType
     * @return string
     * @todo Implement support for $paramType.
     */
    public function quote($string, $paramType = \PDO::PARAM_STR)
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }

    /**
     * Parses a DSN string according to the rules in the PHP manual
     *
     * @param string $dsn
     * @todo Extract this to a DSN Parser object and inject result into PDO class
     * @todo Change return value of array() when invalid to thrown exception
     * @todo Change returned value to object with default values and properties
     * @todo Refactor to use an URI content resolver instead of file_get_contents() that could support caching for example
     * @param array $params
     * @return array
     * @link http://www.php.net/manual/en/pdo.construct.php
     */
    public static function parseDsn($dsn, array $params)
    {

        //If there is a colon, it means it's a parsable DSN
        //Doesn't mean it's valid, but at least, it's parsable
        if (strpos($dsn, ':') !== false) {

            //The driver is the first part of the dsn, then comes the variables
            $driver = null;
            $vars = null;
            @list($driver, $vars) = explode(':', $dsn, 2);

            //Based on the driver, the processing changes
            switch($driver)
            {
                case 'uri':

                    //If the driver is a URI, we get the file content at that URI and parse it
                    return self::parseDsn(file_get_contents($vars), $params);

                case 'oci':

                    //Remove the leading //
                    if(substr($vars, 0, 2) !== '//')
                    {
                        return array();
                    }
                    $vars = substr($vars, 2);

                    //If there is a / in the initial vars, it means we have hostname:port configuration to read
                    $hostname = 'localhost';
                    $port = 1521;
                    if(strpos($vars, '/') !== false)
                    {

                        //Extract the hostname port from the $vars
                        $hostnamePost = null;
                        @list($hostnamePort, $vars) = explode('/', $vars, 2);

                        //Parse the hostname port into two variables, set the default port if invalid
                        @list($hostname, $port) = explode(':', $hostnamePort, 2);
                        if(!is_numeric($port) || is_null($port))
                        {
                            $port = 1521;
                        }
                        else
                        {
                            $port = (int)$port;
                        }

                    }

                    //Extract the dbname/service name from the first part, the rest are parameters
                    @list($dbname, $vars) = explode(';', $vars, 2);
                    $returnParams = array();
                    foreach(explode(';', $vars) as $var)
                    {

                        //Get the key/value pair
                        @list($key, $value) = explode('=', $var, 2);

                        //If the key is not a valid parameter, discard
                        if(!in_array($key, $params))
                        {
                            continue;
                        }

                        //Key that key/value pair
                        $returnParams[$key] = $value;

                    }

                    // Dbname may also contain SID
                    if(strpos($dbname,'/SID/') !== false)
                    {
                        list($dbname, $sidKey, $sidValue) = explode('/',$dbname);
                    }

                    //Condense the parameters, hostname, port, dbname into $returnParams
                    $returnParams['hostname'] = $hostname;
                    $returnParams['port'] = $port;
                    $returnParams['dbname'] = $dbname;
                    if(isset($sidValue)) $returnParams['sid'] = $sidValue;

                    //Return the resulting configuration
                    return $returnParams;

            }

        //If there is no colon, it means it's a DSN name in php.ini
        } elseif (strlen(trim($dsn)) > 0) {

            // The DSN passed in must be an alias set in php.ini
            return self::parseDsn(ini_get("pdo.dsn.".$dsn), $params);

        }

        //Not valid, return an empty array
        return array();

    }

    /**
     * function to check if sequence exists
     * @param  string $name
     * @return boolean
     */
    public function checkSequence($name)
    {
        if (!$name)
            return false;

        $stmt = $this->query("select count(*)
            from all_sequences
            where
                sequence_name=upper('{$name}')
                and sequence_owner=upper(user)
            ");
        return $stmt->fetch();
    }

}
