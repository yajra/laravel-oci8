<?php namespace Jfelder\OracleDB\OCI_PDO;

class OCIStatement extends \PDOStatement
{
    /**
     * @var oci8 statement Statement handle
     */
    protected $stmt;

    /**
     * @var oci8 Database connection
     */
    protected $conn;
    
    /**
     * @var array Database statement attributes
     */
    protected $attributes;
    
    /**
     * @var string SQL statement
     */
    protected $sql = "";
    
    /**
     * @var array SQL statement parameters
     */
    protected $parameters = array();
    
    /**
     * @var array PDO => OCI data types conversion var
     */
    protected $datatypes = array(
        \PDO::PARAM_BOOL => \SQLT_INT, 
        \PDO::PARAM_NULL => \SQLT_INT,
        \PDO::PARAM_INT => \SQLT_INT,
        \PDO::PARAM_STR => \SQLT_CHR,
    );

    /**
     * @var array PDO errorInfo array
     */
    protected $error = array(0 => '', 1 => null, 2 => null);

    /**
     * @var array Array to hold column bindings
     */
    protected $bindings = array();
    
    /**
     * @var array Array to hold descriptors
     */
    protected $descriptors = array();
    
    /**
     * Constructor
     *
     * @param resource $stmt Statement handle created with oci_parse()
     * @param OCI $oci The OCI object for this statement
     * @param array $options Options for the statement handle
     * 
     * @throws OCIException if $stmt is not a vaild oci8 statement resource
     */
    public function __construct($stmt, \Jfelder\OracleDB\OCI_PDO\OCI $oci, $sql = "", $options = array())
    {
        $resource_type = strtolower(get_resource_type($stmt));

        if ($resource_type != 'oci8 statement') {
            throw new OCIException($this->setErrorInfo('0A000', '9999', "Invalid resource received: {$resource_type}"));
        }

        $this->stmt = $stmt;
        $this->conn = $oci;
        $this->sql = $sql;
        $this->attributes = $options;
    }

    /**
     * Destructor - Checks for an oci statment resource and frees the resource if needed
     */
    public function __destruct() 
    {
        if (strtolower(get_resource_type($this->stmt)) == 'oci8 statement') {
            oci_free_statement($this->stmt);
        }

        //Also test for descriptors
    }

    /**
     * Bind a column to a PHP variable
     *
     * @param  mixed $column Number of the column (1-indexed) in the result set
     * @param  mixed $param Name of the PHP variable to which the column will be bound.
     * @param  int $type Data type of the parameter, specified by the PDO::PARAM_* constants.
     * @param  int $maxlen A hint for pre-allocation.
     * @param  mixed $driverdata Optional parameter(s) for the driver.
     *
     * @throws \InvalidArgumentException If an unknown data type is passed in
     * 
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function bindColumn ($column , &$param, $data_type = null, $maxlen = null, $driverdata = null)
    {
        if(!is_numeric($column) || $column < 1)
        {
            throw new \InvalidArgumentException("Invalid column specified: {$column}");
        }
        
        if(!isset($this->datatypes[$data_type])) {
            throw new \InvalidArgumentException("Unknown data type in oci_bind_by_name: {$data_type}");
        }

        $this->bindings[$column] = array('var' => &$param, 
                                        'data_type' => $data_type, 
                                        'max_length' => $maxlen, 
                                        'driverdata' => $driverdata);

        return true;
    }

    /**
     * Binds a parameter to the specified variable name
     *
     * @param  mixed $parameter Parameter identifier
     * @param  mixed $variable Name of the PHP variable to bind to the SQL statement parameter
     * @param  int $data_type Explicit data type for the parameter using the PDO::PARAM_* constants
     * @param  int $length Length of the data type
     * @param  mixed $driver_options 
     *
     * @throws \InvalidArgumentException If an unknown data type is passed in
     * 
     * @return bool Returns TRUE on success or FALSE on failure
     */
    public function bindParam ($parameter , &$variable, $data_type = \PDO::PARAM_STR, $length = -1, $driver_options = null)
    {
        if(is_numeric($parameter))
        {
            $parameter = ":{$parameter}";
        }
        
        $this->addParameter($parameter, $variable, $data_type, $length, $driver_options);

        if(!isset($this->datatypes[$data_type])) {
            if($data_type < 0) {
                $data_type = \PDO::PARAM_STR;
                $length = $length > 40 ? $length : 40;
            } else {
                throw new \InvalidArgumentException("Unknown data type in oci_bind_by_name: {$data_type}");
            }
        }
        
        //Bind the parameter
        $result = oci_bind_by_name($this->stmt, $parameter, $variable, $length, $this->datatypes[$data_type]);

        return $result;
    }

    /**
     * Binds a value to a parameter
     *
     * @param  mixed $parameter Parameter identifier.
     * @param  mixed $value The value to bind to the parameter
     * @param  int $data_type Explicit data type for the parameter using the PDO::PARAM_* constants
     *
     * @throws \InvalidArgumentException If an unknown data type is passed in
     * 
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function bindValue ($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        if(is_numeric($parameter))
        {
            $parameter = ":{$parameter}";
        }

        $this->addParameter($parameter, $value, $data_type);

        if(!isset($this->datatypes[$data_type])) {
            throw new \InvalidArgumentException("Unknown data type in oci_bind_by_name: {$data_type}");
        }
        
        //Bind the parameter
        $result = oci_bind_by_name($this->stmt, $parameter, $value, -1, $this->datatypes[$data_type]);

        return $result;
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     * @todo Implement function
     */
    public function closeCursor()
    {
        return true;
    }

    /**
     * Returns the number of columns in the result set
     *
     * @return int Returns the number of columns in the result set represented by the PDOStatement object. If there is no result set, returns 0.
     */
    public function columnCount()
    {
        return oci_num_fields($this->stmt);
    }

    /**
     * Dump an SQL prepared command
     *
     * @return string print_r of the sql and parameters array
     */
    public function debugDumpParams()
    {    
        return print_r(array('sql' => $this->sql, 'params' => $this->parameters), true);
    }
    
    /**
     * Fetch the SQLSTATE associated with the last operation on the statement handle
     *
     * @return mixed Returns an SQLSTATE or NULL if no operation has been run 
     */
    public function errorCode ()
    {
        if(!empty($this->error[0])) {
            return $this->error[0];
        }
        
        return null;
    }
    
    /**
     * Fetch extended error information associated with the last operation on the statement handle
     *
     * @return array array of error information about the last operation performed
     */
    public function errorInfo ()
    {
        return $this->error;
    }
    
    /**
     * Executes a prepared statement
     *
     * @param  array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function execute ($input_parameters = null)
    {
        if (is_array($input_parameters)) {
            foreach ($input_parameters as $k => $v) {
                $this->bindParam($k, $input_parameters[$k]);
            }
        }

        $result = oci_execute($this->stmt, $this->conn->getExecuteMode());
        if(!$result){
            $this->setErrorInfo('07000');
        }
        
        $this->processBindings($result);

        return $result;
    }

    /**
     * Fetches the next row from a result set
     *
     * @param  int $fetch_style Controls how the next row will be returned to the caller. This value must be one of the PDO::FETCH_* constants
     * @param  int $cursor_orientation For a Statement object representing a scrollable cursor, this value determines which row will be returned to the caller. 
     * @param  int $cursor_offset Specifies the absolute number of the row in the result set that shall be fetched
     *
     * @return mixed The return value of this function on success depends on the fetch type. In all cases, FALSE is returned on failure.
     */
    public function fetch ($fetch_style = \PDO::FETCH_CLASS, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        // set global fetch_style
        $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $fetch_style);
        
        // init return value
        $rs = false;
        
        // determine what oci_fetch_* to run
        switch($fetch_style)
        {
            case \PDO::FETCH_CLASS:
            case \PDO::FETCH_ASSOC:
                $rs = oci_fetch_assoc($this->stmt);
                break;
            case \PDO::FETCH_NUM:
                $rs = oci_fetch_row($this->stmt);
                break;
            default:
                $rs = oci_fetch_array($this->stmt);
                break;
        }
        
        if(!$rs) {
            $this->setErrorInfo('07000');
        }

        $this->processBindings($rs);

        return $this->processFetchOptions($rs);
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param  int $fetch_style Controls how the next row will be returned to the caller. This value must be one of the PDO::FETCH_* constants
     * @param  mixed $fetch_argument This argument have a different meaning depending on the value of the fetch_style parameter
     * @param  array $ctor_args Arguments of custom class constructor when the fetch_style parameter is PDO::FETCH_CLASS.
     *
     * @return [type] [description]
     */
    public function fetchAll ($fetch_style = \PDO::FETCH_CLASS, $fetch_argument = null, $ctor_args = array())
    {
        if ($fetch_style != \PDO::FETCH_CLASS && $fetch_style != \PDO::FETCH_ASSOC) {
            throw new \InvalidArgumentException("Invalid fetch style requested: {$fetch_style}. Only PDO::FETCH_CLASS and PDO::FETCH_ASSOC suported.");
        }

        $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $fetch_style);

        $rs = oci_fetch_all($this->stmt, $temprs, 0, -1, \OCI_FETCHSTATEMENT_BY_ROW + \OCI_ASSOC);
        
        if ($rs !== false) {
            // convert to type requested from PDO options
            foreach($temprs as $k=>$v) {
                $temprs[$k] = $this->processFetchOptions($v);
            }
            
            $rs = $temprs;
        }
        
        if(!$rs) {
            $this->setErrorInfo('07000');
        }

        return $rs;
    }

    /**
     * Returns a single column from the next row of a result set
     *
     * @param  int $column_number 0-indexed number of the column you wish to retrieve from the row. If no value is supplied, fetchColumn fetches the first column.
     *
     * @return mixed single column in the next row of a result set
     */
    public function fetchColumn ($column_number = 0)
    {
        if(!is_int($column_number))
            throw new OCIException($this->setErrorInfo('0A000', '9999', "Invalid Column type specfied: {$column_number}. Expecting an int."));
        
        $rs = $this->fetch(\PDO::FETCH_NUM);
        return isset($rs[$column_number]) ? $rs[$column_number] : false;
    }

    /**
     * Fetches the next row and returns it as an object
     * 
     * @param string $class_name Name of the created class
     * @param array $ctor_args Elements of this array are passed to the constructor
     * @return bool Returns an instance of the required class with property names that correspond to the column names or FALSE on failure.
     */
    public function fetchObject ($class_name = "stdClass", $ctor_args = null)
    {
        $this->setFetchMode(\PDO::FETCH_CLASS, $class_name, $ctor_args);
        return $this->fetch(\PDO::FETCH_CLASS);
    }

    /**
     * Retrieve a statement attribute
     * 
     * @param int $attribute The attribute number
     * @return mixed Returns the value of the attribute on success or null on failure
     */
    public function getAttribute ($attribute)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }
        return null;
    }

    /**
     * Returns metadata for a column in a result set
     * 
     * @param int #column The 0-indexed column in the result set.
     * @return array Returns an associative array representing the metadata for a single column
     */
    public function getColumnMeta ($column)
    {
        if(!is_int($column))
            throw new OCIException($this->setErrorInfo('0A000', '9999', "Invalid Column type specfied: {$column}. Expecting an int."));
        
        $column++;
        
        return array(
            'native_type' => oci_field_type($this->stmt, $column),
            'driver:decl_type' => oci_field_type_raw($this->stmt, $column),
            'name' => oci_field_name($this->stmt, $column),
            'len' => oci_field_size($this->stmt, $column),
            'precision' => oci_field_precision($this->stmt, $column),
        );
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle
     * 
     * @return bool Returns TRUE on success or FALSE on failure
     * @todo Implement method
     */
    public function nextRowset()
    {
        return true;
    }

    /**
     * Returns the number of rows affected by the last SQL statement
     * 
     * @return int Returns the number of rows affected as an integer, or FALSE on errors.
     */
    public function rowCount()
    {
        return oci_num_rows($this->stmt);
    }

    /**
     * Set a statement attribute
     * 
     * @param int $attribute The attribute number
     * @param mixed $value Value of named attribute
     * @return bool Returns TRUE
     */
    public function setAttribute ($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
        return true;
    }

    /**
     * Set the default fetch mode for this statement
     * 
     * @param int $mode The fetch mode must be one of the PDO::FETCH_* constants.
     * @param mixed $type Column number, class name or object depending on PDO::FETCH_* constant used
     * @param array $ctorargs Constructor arguments
     * @return bool Returns TRUE on success or FALSE on failure
     * @todo Implement method
     */
    public function setFetchMode ($mode, $type = null, $ctorargs = array())
    {
        return true;
    }
    
    /**
     * CUSTOM CODE FROM HERE DOWN 
     * 
     * All code above this is overriding the PDO base code
     * All code below this are custom helpers or other functionality provided by the oci_* functions
     * 
     */
    
    /**
     * Stores query parameters for debugDumpParams putput
     */
    private function addParameter($parameter , $variable, $data_type = \PDO::PARAM_STR, $length = -1, $driver_options = null) 
    {
        $param_count = count($this->parameters);
        $this->parameters[$param_count] = array('paramno' => $param_count, 
            'name' => $parameter, 
            'value' => $variable,
            'is_param' => 1, 
            'param_type' => $data_type
        );
    }

    /**
     * Single location to process all the bindings on a resultset
     * 
     * @param array $rs The fetched array to be modified
     */
    private function processBindings($rs)
    {
        if($rs !== false && !empty($this->bindings))
        {
            $i=1;
            foreach ($rs as $col => $value) {
                if(isset($this->bindings[$i])) {
                    $this->bindings[$i]['var'] = $value;
                }
                $i++;
            }
        }
    }

    /**
     * Single location to process all the fetch options on a resultset
     * 
     * @param array $rec The fetched array to be modified
     * @return mixed The modified resultset
     */
    private function processFetchOptions($rec)
    {
        if($rec !== false)
        {
            if ($this->conn->getAttribute(\PDO::ATTR_CASE) == \PDO::CASE_LOWER) $rec = array_change_key_case($rec, \CASE_LOWER);
            $rec = ($this->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE) != \PDO::FETCH_CLASS) ? $rec : (object) $rec;

        }
        return $rec;
    }

    /**
     * Single location to process all errors and set necessary fields 
     * 
     * @param string $code The SQLSTATE error code. defualts to custom 'JF000'
     * @param string $error The driver based error code. If null, oci_error is called
     * @param string $message The error message
     * @return array The local error array 
     */
    private function setErrorInfo($code=null, $error=null, $message = null) 
    {
        if(is_null($code))
            $code = 'JF000';
        
        if(is_null($error)){
            $e = oci_error($this->conn);
            $error = $e['code'];
            $message = $e['message'] . (empty($e['sqltext']) ? '' : ' - SQL: '.$e['sqltext']);
        }

        $this->error[0] = $code;
        $this->error[1] = $error;
        $this->error[2] = $message;            
        
        return $this->error;
    }
}