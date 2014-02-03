<?php
namespace {
    $OCITransactionStatus = true;
}
namespace Jfelder\OracleDB\OCI_PDO {  
    function oci_connect($var) { global $OCITransactionStatus; return $OCITransactionStatus;  } 
    function oci_pconnect($var) { global $OCITransactionStatus; return $OCITransactionStatus;  } 
    function oci_close($var) { global $OCITransactionStatus; $OCITransactionStatus = false; } 
    function oci_commit($var) { global $OCITransactionStatus; return $OCITransactionStatus;  } 
    function oci_rollback($var) { global $OCITransactionStatus; return $OCITransactionStatus;  } 
    if (!function_exists("Jfelder\OracleDB\OCI_PDO\oci_error")) {    
        function oci_error($var="") { return array('code'=>0,'message'=>'', 'sqltext'=>''); } 
    }

use \Mockery as m;

include 'mocks/OCIMocks.php';

class OracleDBOCITest extends \PHPUnit_Framework_TestCase 
{
    private $oci;
    
    // defining here in case oci8 extension not installed
    protected function setUp()
    {
        if (!extension_loaded('oci8')) {
            $this->markTestSkipped(
              'The oci8 extension is not available.'
            );
        } else {
            $this->oci = m::mock(new \TestOCIStub());
        }
    }
    
    public function tearDown()
    {
        m::close();
    }

    public function testConstructorSuccessWithPersistentConnection ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = true;
        $oci = new OCI('dsn', null, null, array(\PDO::ATTR_PERSISTENT=>1));
        $this->assertInstanceOf('Jfelder\OracleDB\OCI_PDO\OCI', $oci);
        $this->assertEquals(1, $oci->getAttribute(\PDO::ATTR_PERSISTENT));
    }
    
    public function testConstructorSuccessWithoutPersistentConnection ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = true;
        $oci = new OCI('dsn', null, null, array(\PDO::ATTR_PERSISTENT=>0));
        $this->assertInstanceOf('Jfelder\OracleDB\OCI_PDO\OCI', $oci);
        $this->assertEquals(0, $oci->getAttribute(\PDO::ATTR_PERSISTENT));
    }
    
    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testConstructorFailWithPersistentConnection ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = false;
        $oci = new OCI('dsn', null, null, array(\PDO::ATTR_PERSISTENT=>1));
    }
    
    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testConstructorFailWithoutPersistentConnection ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = false;
        $oci = new OCI('dsn', null, null, array(\PDO::ATTR_PERSISTENT=>0));
    }
    
    public function testDestructor ()
    {
        // need to mock oci_close
        global $OCITransactionStatus;
        $OCITransactionStatus = true;
        $oci = new OCI('dsn');
        unset($oci);
        $this->assertFalse($OCITransactionStatus);
                
    }
    
    public function testBeginTransaction()
    {
        $result = $this->oci->beginTransaction();        
        $this->assertTrue($result);
        
        $this->assertEquals(0, $this->oci->getExecuteMode());
    }
    
    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testBeginTransactionAlreadyInTransaction()
    {
        $result = $this->oci->beginTransaction();        
        $result = $this->oci->beginTransaction();        
    }

    public function testCommitInTransactionPasses ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = true;
        $this->oci->beginTransaction();
        $this->assertTrue($this->oci->commit());
    }
    
    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testCommitInTransactionFails ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = false;
        $this->oci->beginTransaction();
        $this->oci->commit();
    }
    
    public function testCommitNotInTransaction ()
    {
        $this->assertFalse($this->oci->commit());
    }
    
    // method not yet implemented
    public function testErrorCode ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }
    
    // method not yet implemented
    public function testErrorInfo ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }
    
    // method not yet implemented
    public function testExec ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }
    
    public function testGetAttributeForValidAttribute ()
    {
        $this->assertEquals(1, $this->oci->getAttribute(\PDO::ATTR_AUTOCOMMIT));    
    }
    
    public function testGetAttributeForInvalidAttribute ()
    {
        $this->assertEquals(null, $this->oci->getAttribute('doesnotexist'));    
    }
    
    // method not yet implemented
    public function testGetAvailableDrivers ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }

    public function testInTransactionWhileNotInTransaction()
    {
        $this->assertFalse($this->oci->inTransaction());
    }
    
    public function testInTransactionWhileInTransaction()
    {
        $this->oci->beginTransaction();        
        $this->assertTrue($this->oci->inTransaction());
    }
    
    public function testLastInsertID()
    {
        $result = $this->oci->lastInsertID();        
        $this->assertNull($result);
        
        $result = $this->oci->lastInsertID('foo');        
        $this->assertNull($result);
        
    }
    
    public function testPrepare ()
    {
        // need to mock oci_parse
        $this->markTestSkipped('Test not yet implemented');
    }

    // method not yet implemented
    public function testQuery ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }

    // method not yet implemented
    public function testQuote ()
    {
        $this->markTestSkipped('Test not yet implemented');
    }

    public function testRollBackInTransactionPasses()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = true;
        $this->oci->beginTransaction();
        $this->assertTrue($this->oci->rollBack());
    }

    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testRollBackInTransactionFails ()
    {
        global $OCITransactionStatus;
        $OCITransactionStatus = false;
        $this->oci->beginTransaction();
        $this->oci->rollBack();
    }
    
    public function testRollBackNotInTransaction ()
    {
        $this->assertFalse($this->oci->rollBack());
    }
    
    public function testSetAttribute ()
    {
        $this->oci->setAttribute('attribute', 'value');
        $this->assertEquals('value', $this->oci->getAttribute('attribute'));
        $this->oci->setAttribute('attribute', 4);
        $this->assertEquals(4, $this->oci->getAttribute('attribute'));
    }
    
    public function testGetExecuteMode() 
    {
        $this->assertEquals(\OCI_COMMIT_ON_SUCCESS, $this->oci->getExecuteMode());
    }

    public function testFlipExecuteMode() 
    {
        $this->assertEquals(\OCI_COMMIT_ON_SUCCESS, $this->oci->getExecuteMode());
        $this->oci->flipExecuteMode();
        $this->assertEquals(\OCI_NO_AUTO_COMMIT, $this->oci->getExecuteMode());
    }

    public function testSetExecuteModeWithValidMode() 
    {
        $this->oci->setExecuteMode(\OCI_COMMIT_ON_SUCCESS);
        $this->assertEquals(\OCI_COMMIT_ON_SUCCESS, $this->oci->getExecuteMode());
        $this->oci->setExecuteMode(\OCI_NO_AUTO_COMMIT);
        $this->assertEquals(\OCI_NO_AUTO_COMMIT, $this->oci->getExecuteMode());
    }

    /**
     * @expectedException Jfelder\OracleDB\OCI_PDO\OCIException
     */
    public function testSetExecuteModeWithInvalidMode() 
    {
        $this->oci->setExecuteMode('foo');
    }
}
}