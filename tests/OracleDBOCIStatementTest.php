<?php
namespace {
    $OCIStatementStatus = true;
}
namespace Jfelder\OracleDB\OCI_PDO {  
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_error")) { 
        function oci_error($var="") { return array('code'=>0,'message'=>'', 'sqltext'=>''); } 
    }
    function get_resource_type($a) { global $OCIStatementStatus; return $OCIStatementStatus ? 'oci8 statement' : 'invalid'; }
    function oci_bind_by_name($a, $b, $c, $d, $e) { global $OCIStatementStatus; return $OCIStatementStatus; } 
    function oci_num_fields($var) { return 1;} 
    function oci_free_statement($var) {}
    function oci_execute($a, $b) { global $OCIStatementStatus; return $OCIStatementStatus; }
    function oci_fetch_assoc($a) {}
    function oci_fetch_row($a) {}
    function oci_fetch_array($a) {}
    function oci_fetch_all($a) {}
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
            $this->oci = m::mock(new \TestOCIStub());

            m::getConfiguration()->setInternalClassMethodParamMap(
                    '\TestOCIStatementStub',
                    'bindParam',
                    array('&$variable', '$options=array()')
            );
            $this->stmt = m::mock(new \TestOCIStatementStub(true, $this->oci, array('fake'=>'attribute')));
        }
    }
    
    public function tearDown()
    {
        m::close();
    }

    public function testConstructor ()
    {
        $this->markTestSkipped('Test not yet Implemented');
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
        $this->markTestSkipped('Test not yet Implemented');
    }
    
    // method not yet implemented
    public function testBindColumn ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testBindParamWithValidDataType ()
    {
        $this->markTestSkipped('Test skipped until I canfigure out passing byref :(');
        global $OCIStatementStatus;
        $OCIStatementStatus = true;
        $variable = "";
        $this->assertTrue($this->stmt->bindParam('param', $variable));    
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

    public function testFetch ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testFetchAll ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testFetchColumn ()
    {
        $this->markTestSkipped('Test not yet Implemented');
    }

    public function testFetchObject ()
    {
        $this->markTestSkipped('Test not yet Implemented');
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