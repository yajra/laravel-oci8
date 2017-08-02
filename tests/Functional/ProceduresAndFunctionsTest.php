<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\OracleTypeCaster;

class ProceduresAndFunctionsTest extends PHPUnit_Framework_TestCase
{
    public function testConnection()
    {
        $this->assertInstanceOf(Oci8Connection::class, $this->createConnection());
    }

    public function testProcedureWithNumbers()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->createConnection();

        $procedureName = 'demo';

        $command = "
            CREATE OR REPLACE PROCEDURE demo(p1 IN NUMBER, p2 OUT NUMBER) AS
            BEGIN
                p2 := p1 * 2;
            END;
        ";

        $connection->getPdo()->exec($command);

        $input  = 2;
        $output = 0;

        $bindings = [
            'p1' => $input,
            'p2' => &$output,
        ];

        $connection->executeProcedure($procedureName, $bindings);

        //@todo
        //unfortunately we need to cast here.. any better ideas?
        //internal auto-casting and removing the & reference in the bindings would be nice :)
        $output = OracleTypeCaster::tryNumeric($output);

        $this->assertSame($input * 2, $output);
    }

    public function testProcedureWithStrings()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->createConnection();

        $procedureName = 'demo';

        $command = "
            CREATE OR REPLACE PROCEDURE demo(p1 IN VARCHAR2, p2 IN VARCHAR2, p3 OUT VARCHAR2) AS
            BEGIN
                p3 := p1 || p2;
            END;
        ";

        $connection->getPdo()->exec($command);

        $first = 'hello';
        $last  = 'world';

        //this needs to be large enough to hold the plsql return value
        $output = str_repeat('', 1000);

        $bindings = [
            'p1' => $first,
            'p2' => $last,
            'p3' => &$output,
        ];

        $connection->executeProcedure($procedureName, $bindings);

        $this->assertSame($first . $last, $output);
    }

    public function testRefCursorFromTable()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->createConnection();

        $procedureName = 'demo';

        $connection->getSchemaBuilder()->dropIfExists('demotable');
        $connection->getSchemaBuilder()->create('demotable', function (Blueprint $table) {
            $table->string('name');
        });

        $rows = [
            [
                'name' => 'Max'
            ],
            [
                'name' => 'John'
            ]
        ];
        $connection->table('demotable')->insert($rows);

        $command = "
            CREATE OR REPLACE PROCEDURE demo(p1 OUT SYS_REFCURSOR) AS
            BEGIN
                OPEN p1 
                FOR 
                SELECT name
                FROM demotable; 
            END;
        ";

        $connection->getPdo()->exec($command);

        $result = $connection->executeProcedureWithCursor($procedureName);

        $this->assertSame($rows, $result);
    }


    public function testFunctionWithNumbers()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->createConnection();

        $procedureName = 'add_two';

        $command = "
            CREATE OR REPLACE FUNCTION  add_two (p1 IN NUMBER) RETURN NUMBER IS
            BEGIN
                 RETURN p1 + 2;
            END;
        ";

        $connection->getPdo()->exec($command);

        $first    = 5;
        $bindings = [
            'p1' => $first,
        ];

        $result = $connection->executeFunction($procedureName, $bindings);

        $this->assertSame($first + 2, $result);
    }

    /**
     * @return \Illuminate\Database\Connection
     */
    private function createConnection()
    {
        $capsule = new Capsule;

        $manager = $capsule->getDatabaseManager();
        $manager->extend('oracle', function ($config) {
            $connector  = new OracleConnector();
            $connection = $connector->connect($config);
            $db         = new Oci8Connection($connection, $config["database"], $config["prefix"]);

            // set oracle session variables
            $sessionVars = [
                'NLS_TIME_FORMAT'         => 'HH24:MI:SS',
                'NLS_DATE_FORMAT'         => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT'    => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_TZ_FORMAT' => 'YYYY-MM-DD HH24:MI:SS TZH:TZM',
                'NLS_NUMERIC_CHARACTERS'  => '.,',
            ];

            // Like Postgres, Oracle allows the concept of "schema"
            if (isset($config['schema'])) {
                $sessionVars['CURRENT_SCHEMA'] = $config['schema'];
            }

            $db->setSessionVars($sessionVars);

            return $db;
        });

        $capsule->addConnection([
            'driver'       => 'oracle',
            'host'         => 'localhost',
            'database'     => 'xe',
            'service_name' => 'xe',
            'username'     => 'system',
            'password'     => 'oracle',
            'prefix'       => '',
            'port'         => 49161
        ]);

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();


        return $capsule->getConnection();
    }
}
