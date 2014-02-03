<?php
namespace {
    $OCIStatementStatus = true;
}
namespace Jfelder\OracleDB\OCI_PDO {  
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_error")) { 
        function oci_error($var="") { return array('code'=>0,'message'=>'', 'sqltext'=>''); } 
    }
    function get_resource_type($a) { global $OCIStatementStatus; return $OCIStatementStatus ? 'oci8 statement' : 'invalid'; }
    function oci_bind_by_name($a, $b, &$c, $d, $e) { global $OCIStatementStatus; $c = 'oci_bind_by_name'; return $OCIStatementStatus; } 
    function oci_num_fields($var) { return 1;} 
    function oci_free_statement($var) { global $OCIStatementStatus; $OCIStatementStatus = false; }
    function oci_execute($a, $b) { global $OCIStatementStatus; return $OCIStatementStatus; }
    function oci_fetch_assoc($a) { global $OCIStatementStatus; return $OCIStatementStatus ? array('FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com') : false; }
    function oci_fetch_row($a) { global $OCIStatementStatus; return $OCIStatementStatus ? array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com') : false; }
    function oci_fetch_array($a) { global $OCIStatementStatus; return $OCIStatementStatus ? array(0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com', 'FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com') : false; }
    function oci_fetch_all($a, &$b) { global $OCIStatementStatus; $b = array(array('FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com')); return $OCIStatementStatus; }
    function oci_field_type($a, $b) { return 1; }
    function oci_field_type_raw($a, $b) { return 1; }
    function oci_field_name($a, $b) { return 1; }
    function oci_field_size($a, $b) { return 1; }
    function oci_field_precision($a, $b) { return 1; }
    function oci_num_rows($a) { return 1; }
    

use \Mockery as m;

include 'mocks/OCIMocks.php';

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
            //$this->oci = m::mock(new \TestOCIStub());
            //$this->stmt = m::mock(new \TestOCIStatementStub(true, $this->oci, array('fake'=>'attribute')));
            $this->oci = m::mock(new \TestOCIStub('', null, null, array(\PDO::ATTR_CASE => \PDO::CASE_LOWER)));
            $this->stmt = m::mock(new \TestOCIStatementStub(true, $this->oci, array('fake'=>'attribute')));
            
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
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $oci = new \TestOCIStub();
        $ocistmt = new OCIStatement(array(), $oci);
        
        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);
        
        // stmt property
        $property = $reflection->getProperty('stmt');
        $property->setAccessible(true);
        $this->assertEquals(array(), $property->getValue($ocistmt));
        
        $property = $reflection->getProperty('conn');
        $property->setAccessible(true);
        $this->assertEquals($oci, $property->getValue($ocistmt));

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
        $ocistmt = new OCIStatement(array(), new \TestOCIStub());
    }
    
    public function testDestructor ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $ocistmt = new OCIStatement(array(), new \TestOCIStub());
        unset($ocistmt);
        $this->assertFalse($OCIStatementStatus);
    }
    
    // method not yet implemented
    public function testBindColumn ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testBindParamWithValidDataType ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $variable = "";
        
        $stmt = new \TestOCIStatementStub(true, new \TestOCIStub(), array());
        $this->assertTrue($stmt->bindParam('param', $variable));
        $this->assertEquals('oci_bind_by_name', $variable);
    }

    public function testBindValueWithValidDataType ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
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
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testColumnCount()
    {
        $this->assertEquals(1, $this->stmt->columnCount());
    }

    // method not yet implemented
    public function testDebugDumpParams()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    // method not yet implemented
    public function testErrorCode()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    // method not yet implemented
    public function testErrorInfo()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testExecutePassesWithParameters ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $this->assertTrue($this->stmt->execute(array(0=>1)));
    }

    public function testExecutePassesWithoutParameters ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $this->assertTrue($this->stmt->execute());
    }

    public function testExecuteFailesWithParameters ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = false;
        $this->assertFalse($this->stmt->execute(array(0=>1)));
    }

    public function testExecuteFailesWithoutParameters ()
    {
        global $OCIStatementStatus;
        $OCIStatementStatus = false;
        $this->assertFalse($this->stmt->execute());
    }

    public function testFetchSuccessReturnArray ()
    {
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;
        
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
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;

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
        global $OCIStatementStatus; 
        $OCIStatementStatus = false;
        $this->assertFalse($this->stmt->fetch());
    }

    public function testFetchAllSuccessReturnArray ()
    {
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;
        
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
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;

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
        global $OCIStatementStatus; 
        $OCIStatementStatus = false;
        $this->assertFalse($this->stmt->fetchAll());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchAllFailWithInvalidFetchStyle ()
    {
        global $OCIStatementStatus; 
        $OCIStatementStatus = false;
        $this->assertFalse($this->stmt->fetchAll(\PDO::FETCH_BOTH));
    }

    public function testFetchColumnWithColumnNumber ()
    {
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;

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
        global $OCIStatementStatus; 
        $OCIStatementStatus = true;

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

    // method not yet implemented
    public function testNextRowset()
    {
        $this->markTestSkipped('Test not yet Implemented');
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

    // method not yet implemented
    public function testSetFetchMode ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }
}
}