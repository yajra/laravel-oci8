<?php

class Oci8Test extends PHPUnit_Framework_TestCase
{

    /**
     * Tests the constructor
     * @dataProvider testParseValidDSNProvider
     */
    public function testParseValidDSN($dsn, $expectedResult)
    {

        //Parse the $dsn and compare to result
        $result = \yajra\Oci8\Connectors\Oci8::parseDsn($dsn, array('charset'));
        $this->assertEquals($expectedResult, $result);

    }

    /**
     * Returns all possible test variations for parseValidDSN
     *
     * @access public
     */
    public function testParseValidDSNProvider()
    {
        return array(
            array(
                'dsn' => 'oci://db1',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1521,
                    'dbname' => 'db1',
                ),
            ),
            array(
                'dsn' => 'oci://localhost/db1',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1521,
                    'dbname' => 'db1',
                ),
            ),
            array(
                'dsn' => 'oci://localhost:1599/db2',
                'expectedResult' => array(
                    'hostname' => 'localhost',
                    'port' => 1599,
                    'dbname' => 'db2',
                ),
            ),
            array(
                'dsn' => 'oci://nunc1m.server.business:1599/db3',
                'expectedResult' => array(
                    'hostname' => 'nunc1m.server.business',
                    'port' => 1599,
                    'dbname' => 'db3',
                ),
            ),
            array(
                'dsn' => 'oci://nunc1m.server.business:1199/db6;charset=WE8ISO8859P15',
                'expectedResult' => array(
                    'hostname' => 'nunc1m.server.business',
                    'port' => 1199,
                    'dbname' => 'db6',
                    'charset' => 'WE8ISO8859P15'
                ),
            ),
        );
    }

}