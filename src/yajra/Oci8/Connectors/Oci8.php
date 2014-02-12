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

use \PDO;
use yajra\Oci8\Connectors\Oci8\Oci8Exception;
use yajra\Oci8\Connectors\Oci8\Oci8Statement;

/**
 * Oci8 class to mimic the interface of the PDO class
 *
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8 extends PDO
{

    /**
     * Database handler
     *
     * @var resource
     */
    public $connection;

    /**
     * Driver options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Whether currently in a transaction
     *
     * @var bool
     */
    protected $isTransaction = false;

    /**
     * insert query statement table variable
     *
     * @var string
     */
    protected $table;

    /**
     * Constructor
     *
     * @param string $dsn
     * @param array $config
     * @param array $options
     * @return void
     */
    public function __construct($dsn, array $config, array $options = array())
    {
        // get credentials
        $username = $config['username'];
        $password = $config['password'];

        //Attempt a connection
        if (isset($options[\PDO::ATTR_PERSISTENT]) && $options[\PDO::ATTR_PERSISTENT]) {
            $this->connection = @oci_pconnect($username, $password, $dsn, $config['charset']);
        } else {
            $this->connection = @oci_connect($username, $password, $dsn, $config['charset']);
        }

        //Check if connection was successful
        if (!$this->connection) {
            $e = oci_error();
            throw new Oci8Exception($e['message']);
        }

        //Save the options
        $this->options = $options;

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
        if($options == null) $options = $this->options;
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
            $this->table = $matches[1];
        }

        //Prepare the statement
        $sth = @oci_parse($this->connection, $statement);

        if (!$sth) {
            $e = oci_error($this->connection);
            throw new Oci8Exception($e['message']);
        }

        if (!is_array($options)) {
            $options = array();
        }

        return new Oci8Statement($sth, $this, $options);
    }

    /**
     * Begins a transaction (turns off autocommit mode)
     *
     * @return void
     */
    public function beginTransaction()
    {
        if ($this->isTransaction()) {
            throw new Oci8Exception('There is already an active transaction');
        }

        $this->isTransaction = true;
        return true;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->isTransaction;
    }

    /**
     * Commits all statements issued during a transaction and ends the transaction
     *
     * @return bool
     */
    public function commit()
    {
        if (!$this->isTransaction()) {
            throw new Oci8Exception('There is no active transaction');
        }

        if (oci_commit($this->connection)) {
            $this->isTransaction = false;
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
            throw new Oci8Exception('There is no active transaction');
        }

        if (oci_rollback($this->connection)) {
            $this->isTransaction = false;
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
        $this->options[$attribute] = $value;
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
    public function query($query, $fetchType = null, $typeArg = null, array $ctorArgs = array())
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
        $sequence = $this->table . "_" . $name . "_seq";
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
        $e = oci_error($this->connection);

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
        if (isset($this->options[$attribute])) {
            return $this->options[$attribute];
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
        return oci_new_cursor($this->connection);
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
        return oci_new_descriptor($this->connection, $type);
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
