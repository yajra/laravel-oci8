<?php
namespace Jfelder\OracleDB\OCI_PDO {  

use \Mockery as m;

include 'mocks/OCIMocks.php';
include 'mocks/OCIFunctions.php';

class OracleDBOCIStatementTest extends \PHPUnit_Framework_TestCase 
{
    // defining here in case oci8 extension not installed
    protected function setUp()
    {
        if (!extension_loaded('oci8')) {
            $this->markTestSkipped(
              'The oci8 extension is not available.'
            );
        } else {
            global $OCIStatementStatus, $OCIExecuteStatus, $OCIFetchStatus, $OCIBindChangeStatus;

            $OCIStatementStatus = true;
            $OCIExecuteStatus = true;
            $OCIFetchStatus = true;
            $OCIBindChangeStatus = false;

            $this->oci = m::mock(new \TestOCIStub('', null, null, array(\PDO::ATTR_CASE => \PDO::CASE_LOWER)));
            $this->stmt = m::mock(new \TestOCIStatementStub('oci8 statement', $this->oci, '', array('fake'=>'attribute')));
            
            //fake result sets for all the fetch calls
            $this->resultUpperArray = array('FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com');
            $this->resultUpperObject = (object) $this->resultUpperArray;
            $this->resultLowerArray = array_change_key_case($this->resultUpperArray, \CASE_LOWER);
            $this->resultLowerObject = (object) $this->resultLowerArray;

            $this->resultNumArray = array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com');
            
            $this->resultBothUpperArray = array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com', 'FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com');
            $this->resultBothLowerArray = array_change_key_case($this->resultBothUpperArray, \CASE_LOWER);

            $this->resultAllUpperArray = array($this->resultUpperArray);
            $this->resultAllUpperObject = array($this->resultUpperObject);
            $this->resultAllLowerArray = array($this->resultLowerArray);
            $this->resultAllLowerObject = array($this->resultLowerObject);

            $this->resultAllNumArray = array($this->resultNumArray);
            
            $this->resultAllBothUpperArray = array($this->resultBothUpperArray);
            $this->resultAllBothLowerArray = array($this->resultBothLowerArray);
}
    }
    
    public function tearDown()
    {
        m::close();
    }

    public function testConstructor ()
    {
        $oci = new \TestOCIStub();
        $ocistmt = new OCIStatement('oci8 statement', $oci);
        
        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);
        
        // stmt property
        $property = $reflection->getProperty('stmt');
        $property->setAccessible(true);
        $this->assertEquals('oci8 statement', $property->getValue($ocistmt));
        
        //conn property
        $property = $reflection->getProperty('conn');
        $property->setAccessible(true);
        $this->assertEquals($oci, $property->getValue($ocistmt));

        //attributes property
        $property = $reflection->getProperty('attributes');
        $property->setAccessible(true);
        $this->assertEquals(array(), $property->getValue($ocistmt));
    }
    
    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testConstructorWithoutValidStatementPassignIn ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = false;
        $ocistmt = new OCIStatement('oci8 statement', new \TestOCIStub());
    }
    
    public function testDestructor ()
    {
        global $OCIStatementStatus;
        $ocistmt = new OCIStatement('oci8 statement', new \TestOCIStub());
        unset($ocistmt);
        $this->assertFalse($OCIStatementStatus);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBindColumnWithColumnName ()
    {
        $stmt = new \TestOCIStatementStub('oci8 statement', $this->oci, 'sql', array());
        $holder = "";
        $stmt->bindColumn('holder', $holder, \PDO::PARAM_STR);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBindColumnWithColumnNumberLessThanOne ()
    {
        $stmt = new \TestOCIStatementStub('oci8 statement', $this->oci, 'sql', array());
        $holder = "";
        $stmt->bindColumn(0, $holder, \PDO::PARAM_STR);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBindColumnWithInvalidDataType ()
    {
        $stmt = new \TestOCIStatementStub('oci8 statement', $this->oci, 'sql', array());
        $holder = "";
        $stmt->bindColumn(1, $holder, 'hello');
    }

    public function testBindColumnSuccess ()
    {
        $stmt = new \TestOCIStatementStub('oci8 statement', $this->oci, 'sql', array());
        $holder = "";
        $this->assertTrue($stmt->bindColumn(1, $holder, \PDO::PARAM_STR, 40));

        $reflection = new \ReflectionClass($stmt);

        // bindings property
        $property = $reflection->getProperty('bindings');
        $property->setAccessible(true);
        $this->assertEquals(array(1 => array('var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null)), $property->getValue($stmt));


    }

    public function testBindParamWithValidDataType ()
    {
        global $OCIBindChangeStatus;
        $OCIBindChangeStatus = true;
        $variable = "";
        
        $stmt = new \TestOCIStatementStub(true, new \TestOCIStub(), '', array());
        $this->assertTrue($stmt->bindParam('param', $variable));
        $this->assertEquals('oci_bind_by_name', $variable);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBindParamWithInvalidDataType ()
    {
        $variable = "";
        
        $stmt = new \TestOCIStatementStub(true, new \TestOCIStub(), '', array());
        $this->assertTrue($stmt->bindParam('param', $variable, 'hello'));
    }

    public function testBindParamWithReturnDataType ()
    {
        global $OCIBindChangeStatus;
        $OCIBindChangeStatus = true;
        $variable = "";
        
        $stmt = new \TestOCIStatementStub(true, new \TestOCIStub(), '', array());
        $this->assertTrue($stmt->bindParam('param', $variable, \PDO::PARAM_INPUT_OUTPUT));
        $this->assertEquals('oci_bind_by_name', $variable);
    }

    public function testBindValueWithValidDataType ()
    {
        $this->assertTrue($this->stmt->bindValue('param', 'hello'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBindValueWithInvalidDataType ()
    {
        $this->stmt->bindValue(0, 'hello', 8);
    }

    // method not yet implemented
    public function testCloseCursor()
    {
        $this->assertTrue($this->stmt->closeCursor());
    }

    public function testColumnCount()
    {
        $this->assertEquals(1, $this->stmt->columnCount());
    }

    public function testDebugDumpParams()
    {
        global $OCIBindChangeStatus;
        $OCIBindChangeStatus = false;

        $this->assertEquals(print_r(array('sql' => '', 'params' => array()), true), $this->stmt->debugDumpParams());
        $stmt = new \TestOCIStatementStub(true, true, 'select * from table where id = :0 and name = :1', array());
        $var = 'Hello';
        
        $stmt->bindParam(0, $var, \PDO::PARAM_INPUT_OUTPUT);
        $stmt->bindValue(1, 'hi');
        $this->assertEquals(print_r(array('sql' => 'select * from table where id = :0 and name = :1', 
            'params' => array(
                array('paramno' => 0,
                    'name' => ':0', 
                    'value' => $var,
                    'is_param' => 1, 
                    'param_type' => \PDO::PARAM_INPUT_OUTPUT
                ), 
                array('paramno' => 1,
                    'name' => ':1', 
                    'value' => 'hi',
                    'is_param' => 1, 
                    'param_type' => \PDO::PARAM_STR
                )
            )), true), $stmt->debugDumpParams()
        );
    }
    
    public function testErrorCode ()
    {
        $ocistmt = new \TestOCIStatementStub(true, '', '', array());
        $this->assertNull($ocistmt->errorCode());

        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);
        
        // setErrorInfo
        $method = $reflection->getMethod('setErrorInfo');
        $method->setAccessible(true);
        $method->invoke($ocistmt, '11111', '2222', 'Testing the errors');
        
        $this->assertEquals('11111', $ocistmt->errorCode());        
    }
    
    public function testErrorInfo ()
    {
        $ocistmt = new \TestOCIStatementStub(true, '', '', array());
        $this->assertEquals(array(0 => '', 1 => null, 2 => null), $ocistmt->errorInfo());

        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);
        
        // setErrorInfo
        $method = $reflection->getMethod('setErrorInfo');
        $method->setAccessible(true);
        $method->invoke($ocistmt, '11111', '2222', 'Testing the errors');
        
        $this->assertEquals(array(0 => '11111', 1 => '2222', 2 => 'Testing the errors'), $ocistmt->errorInfo());        
    }
    
    public function testExecutePassesWithParameters ()
    {
        $this->assertTrue($this->stmt->execute(array(0=>1)));
    }

    public function testExecutePassesWithoutParameters ()
    {
        $this->assertTrue($this->stmt->execute());
    }

    public function testExecuteFailesWithParameters ()
    {
        global $OCIExecuteStatus;
        $OCIExecuteStatus = false;
        $this->assertFalse($this->stmt->execute(array(0=>1)));
        $this->assertEquals('07000',$this->stmt->errorCode());
    }

    public function testExecuteFailesWithoutParameters ()
    {
        global $OCIExecuteStatus;
        $OCIExecuteStatus = false;
        $this->assertFalse($this->stmt->execute());
        $this->assertEquals('07000',$this->stmt->errorCode());
    }

    public function testFetchWithBindColumn()
    {
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $stmt = new \TestOCIStatementStub('oci8 statement', $this->oci, 'sql', array());
        $holder = "dad";
        $this->assertTrue($stmt->bindColumn(1, $holder, \PDO::PARAM_STR, 40));

        $reflection = new \ReflectionClass($stmt);

        // bindings property
        $property = $reflection->getProperty('bindings');
        $property->setAccessible(true);
        $this->assertEquals(array(1 => array('var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null)), $property->getValue($stmt));

        $obj = $stmt->fetch(\PDO::FETCH_CLASS);

        $this->assertEquals(array(1 => array('var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null)), $property->getValue($stmt));

        $this->assertEquals($obj->fname, $holder);
    }

    public function testFetchSuccessReturnArray ()
    {
        // return lower case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothLowerArray, $this->stmt->fetch(\PDO::FETCH_BOTH));
        
        // return upper cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothUpperArray, $this->stmt->fetch(\PDO::FETCH_BOTH));

        // return natural keyed object, in oracle that is upper case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothUpperArray, $this->stmt->fetch(\PDO::FETCH_BOTH));

        $this->assertEquals($this->resultNumArray, $this->stmt->fetch(\PDO::FETCH_NUM));
    }

    public function testFetchSuccessReturnObject ()
    {
        // return lower cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerObject, $this->stmt->fetch(\PDO::FETCH_CLASS));
        
        // return upper cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetch(\PDO::FETCH_CLASS));

        // return natural keyed object, in oracle that is upper case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetch(\PDO::FETCH_CLASS));
    }
    
    public function testFetchFail ()
    {
        global $OCIFetchStatus; 
        $OCIFetchStatus = false;
        $this->assertFalse($this->stmt->fetch());
        $this->assertEquals('07000',$this->stmt->errorCode());
    }

    public function testFetchAllSuccessReturnArray ()
    {
        // return lower case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultAllLowerArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));
        
        // return upper cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultAllUpperArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));

        // return natural keyed object, in oracle that is upper case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultAllUpperArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testFetchAllSuccessReturnObject ()
    {
        // return lower cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultAllLowerObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));
        
        // return upper cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultAllUpperObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));

        // return natural keyed object, in oracle that is upper case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultAllUpperObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));
    }

    public function testFetchAllFail ()
    {
        global $OCIFetchStatus; 
        $OCIFetchStatus = false;
        $this->assertFalse($this->stmt->fetchAll());
        $this->assertEquals('07000',$this->stmt->errorCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchAllFailWithInvalidFetchStyle ()
    {
        $this->stmt->fetchAll(\PDO::FETCH_BOTH);
    }

    public function testFetchColumnWithColumnNumber ()
    {
        $this->assertEquals($this->resultNumArray[1], $this->stmt->fetchColumn(1));
    }

    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testFetchColumnWithColumnName ()
    {
        $this->stmt->fetchColumn('ColumnName');
    }

    public function testFetchObject ()
    {
        // return lower cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerObject, $this->stmt->fetchObject());
        
        // return upper cased keyed object
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetchObject());

        // return natural keyed object, in oracle that is upper case
        $this->oci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetchObject());
    }

    public function testGetAttributeForValidAttribute ()
    {
        $this->assertEquals('attribute', $this->stmt->getAttribute('fake'));    
    }

    public function testGetAttributeForInvalidAttribute ()
    {
        $this->assertEquals(null, $this->stmt->getAttribute('invalid'));    
    }

    public function testGetColumnMetaWithColumnNumber ()
    {
        $expected = array('native_type' => 1, 'driver:decl_type' => 1,
            'name' => 1, 'len' => 1, 'precision' => 1, );
        
        $result = $this->stmt->getColumnMeta(0);
        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testGetColumnMetaWithColumnName ()
    {
        $this->stmt->getColumnMeta('ColumnName');
    }

    public function testNextRowset()
    {
        $this->assertTrue($this->stmt->nextRowset());
    }

    public function testRowCount()
    {
        $this->assertEquals(1, $this->stmt->rowCount());
    }

    public function testSetAttribute ()
    {
        $this->assertTrue($this->stmt->setAttribute('testing', 'setAttribute'));    
        $this->assertEquals('setAttribute', $this->stmt->getAttribute('testing'));    
    }

    public function testSetFetchMode ()
    {
        $this->assertTrue($this->stmt->setFetchMode(\PDO::FETCH_CLASS));
    }

    public function testGetOCIResource() 
    {
        $this->assertEquals('oci8 statement', $this->stmt->getOCIResource());
    }
}
}