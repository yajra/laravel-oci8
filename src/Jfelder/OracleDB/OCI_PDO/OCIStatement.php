<?php namespace Jfelder\OracleDB\OCI_PDO;

class OCIStatement extends \PDOStatement
{
    /*
     * Database statement 
     * 
     * @var oci8 statement
     */
    protected $stmt;

    /*
     * Database connection
     * 
     * @var oci8
     */
    protected $conn;
    
    /*
     * Database statement attributes
     * 
     * @var array
     */
    protected $attributes;
    
    /*
     * PDO => OCI data types conversion var
     * 
     * @var array
     */
    protected $datatypes = array(
        \PDO::PARAM_BOOL => \SQLT_INT, 
        \PDO::PARAM_NULL => \SQLT_INT,
        \PDO::PARAM_INT => \SQLT_INT,
        \PDO::PARAM_STR => \SQLT_CHR,
    );
    
    /**
     * Constructor
     *
     * @param resource $stmt Statement handle created with oci_parse()
     * @param \Jfelder\OCI_PDO\OCI $oci The \Jfelder\OCI_PDO\OCI object for this statement
     * @param array $options Options for the statement handle
     * @return void
     */
    public function __construct($stmt, \Jfelder\OracleDB\OCI_PDO\OCI $oci, $options = array())
    {
        $resource_type = strtolower(get_resource_type($stmt));

        if ($resource_type != 'oci8 statement') {
            throw new OCIException(array('code'=>0,'message'=>"Invalid resource received: {$resource_type}", 'sqltext'=>""));
        }

        $this->stmt = $stmt;
        $this->conn = $oci;
        $this->attributes = $options;
    }

    public function __destruct() 
    {
        oci_free_statement($this->stmt);
    }

    public function bindColumn ($column , &$param, $type = null, $maxlen = null, $driverdata = null)
    {}

    public function bindParam ($parameter , &$variable, $data_type = \PDO::PARAM_STR, $length = -1, $driver_options = null)
    {
        //Replace ? parameter
        if(is_numeric($parameter))
        {
            $parameter = ":{$parameter}";
        }
        
        if(!isset($this->datatypes[$data_type])) {
            if($data_type < 0) {
                $data_type = \PDO::PARAM_STR;
                $length = $length > 40 ? $length : 40;
            } else {
                throw new \InvalidArgumentException("Unknown data type in oci_bind_by_name: {$data_type}");
            }
        }
        //handle lobs
        //handle cursors
        
        //Bind the parameter
        $result = oci_bind_by_name($this->stmt, $parameter, $variable, $length, $this->datatypes[$data_type]);

        return $result;
    }

    public function bindValue ($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        //Replace ? parameter
        if(is_numeric($parameter))
        {
            $parameter = ":{$parameter}";
        }
        
        if(!isset($this->datatypes[$data_type])) {
            throw new \InvalidArgumentException("Unknown data type in oci_bind_by_name: {$data_type}");
        }

        //Bind the parameter
        $result = oci_bind_by_name($this->stmt, $parameter, $value, -1, $this->datatypes[$data_type]);

        return $result;
    }

    public function closeCursor()
    {}

    public function columnCount()
    {
        return oci_num_fields($this->stmt);
    }

    public function debugDumpParams()
    {    }

    public function errorCode()
    {}

    public function errorInfo()
    {}

    public function execute ($input_parameters = null)
    {
        if (is_array($input_parameters)) {
            foreach ($input_parameters as $k => $v) {
                $this->bindParam($k, $input_parameters[$k]);
            }
        }
        $result = oci_execute($this->stmt, $this->conn->getExecuteMode());
        
        return $result;
    }

    public function fetch ($fetch_style = \PDO::FETCH_CLASS, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        // set global fetch_style
        $this->setAttribute('fetch_style', $fetch_style);
        
        // init return value
        $rs = false;
        
        // determine what oci_fetch_* to run
        switch($this->getAttribute('fetch_style'))
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

        return $this->processFetchOptions($rs);
    }

    public function fetchAll ($fetch_style = \PDO::FETCH_CLASS, $fetch_argument = null, $ctor_args = array())
    {
        $this->setAttribute('fetch_style', $fetch_style);

        $rs = oci_fetch_all($this->stmt, $temprs, 0, -1, \OCI_FETCHSTATEMENT_BY_ROW + \OCI_ASSOC);
        
        if ($rs !== false) {
            // convert to type requested from PDO options
            foreach($temprs as $k=>$v) {
                $temprs[$k] = $this->processFetchOptions($v);
            }
            
            $rs = $temprs;
        }
        
        return $rs;
    }

    public function fetchColumn ($column_number = 0)
    {
        $rs = $this->fetch(\PDO::FETCH_ASSOC);
        return isset($rs[$column_number]) ? $rs[$column_number] : false;
    }

    public function fetchObject ($class_name = "stdClass", $ctor_args = null)
    {
        return $this->fetch();
    }

    public function getAttribute ($attribute)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }
        return null;
    }

    public function getColumnMeta ($column)
    {
        if(!is_int($column))
            throw new OCIException(array('code'=>0,'message'=>"Invalid Column type specfied: {$column}. Expecting an int.", 'sqltext'=>""));
        
        $column++;
        
        return array(
            'native_type' => oci_field_type($this->stmt, $column),
            'driver:decl_type' => oci_field_type_raw($this->stmt, $column),
            'name' => oci_field_name($this->stmt, $column),
            'len' => oci_field_size($this->stmt, $column),
            'precision' => oci_field_precision($this->stmt, $column),
        );
    }

    public function nextRowset()
    {}

    public function rowCount()
    {
        return oci_num_rows($this->stmt);
    }

    public function setAttribute ($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
        return true;
    }

    public function setFetchMode ($mode, $params = null)
    {}
    
    private function processFetchOptions($rec)
    {
        if($rec !== false)
        {
            //if ($this->returnLobs) {
            //  foreach ($rs as $field => $value) {
            //      if (is_object($value) ) {
            //          $rs[$field] = $value->load();

            if ($this->conn->getAttribute(\PDO::ATTR_CASE) == \PDO::CASE_LOWER) $rec = array_change_key_case($rec, \CASE_LOWER);
            $rec = ($this->getAttribute('fetch_style') != \PDO::FETCH_CLASS) ? $rec : (object) $rec;
        }
        return $rec;
    }
}