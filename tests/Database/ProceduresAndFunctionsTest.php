<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PDO;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Oci8\Oci8Connection;

class ProceduresAndFunctionsTest extends TestCase
{
    public function testConnection()
    {
        $this->assertInstanceOf(Oci8Connection::class, $this->createConnection());
    }

    /**
     * @param string $prefix
     * @return \Illuminate\Database\Connection|\Yajra\Oci8\Oci8Connection
     */
    private function createConnection($prefix = '')
    {
        $capsule = new Capsule;

        $manager = $capsule->getDatabaseManager();
        $manager->extend('oracle', function ($config) {
            $connector  = new OracleConnector();
            $connection = $connector->connect($config);
            $db         = new Oci8Connection($connection, $config['database'], $config['prefix']);

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
            'prefix'       => $prefix,
            'port'         => 49161,
        ]);

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

        return $capsule->getConnection();
    }

    public function testProcedureWithNumbers()
    {
        $connection = $this->createConnection();

        $procedureName = 'demo';

        $command = '
            CREATE OR REPLACE PROCEDURE demo(p1 IN NUMBER, p2 OUT NUMBER) AS
            BEGIN
                p2 := p1 * 2;
            END;
        ';

        $connection->getPdo()->exec($command);

        $input  = 20;
        $output = 0;

        $bindings = [
            'p1' => $input,
            'p2' => [
                'value' => &$output,
                'type'  => PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT,
            ],
        ];

        $connection->executeProcedure($procedureName, $bindings);

        $this->assertSame($input * 2, $output);
    }

    public function testProcedureWithStrings()
    {
        $connection = $this->createConnection();

        $procedureName = 'demo';

        $command = '
            CREATE OR REPLACE PROCEDURE demo(p1 IN VARCHAR2, p2 IN VARCHAR2, p3 OUT VARCHAR2) AS
            BEGIN
                p3 := p1 || p2;
            END;
        ';

        $connection->getPdo()->exec($command);

        $first = 'hello';
        $last  = 'world';

        //this needs to be large enough to hold the plsql return value
        $output = str_repeat(' ', 1000);

        $bindings = [
            'p1' => $first,
            'p2' => $last,
            'p3' => [
                'value' => &$output,
                'type'  => PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT,
            ],
        ];

        $connection->executeProcedure($procedureName, $bindings);

        $this->assertSame($first . $last, $output);
    }

    public function testRefCursorFromTable()
    {
        $connection = $this->createConnection();

        $this->setupDemoTable($connection);

        $procedureName = 'demo';

        $command = '
            CREATE OR REPLACE PROCEDURE demo(p1 OUT SYS_REFCURSOR) AS
            BEGIN
                OPEN p1
                FOR
                SELECT name
                FROM demotable;
            END;
        ';

        $connection->getPdo()->exec($command);

        $result = $connection->executeProcedureWithCursor($procedureName);
        $rows   = $this->getTestData();

        $this->assertSame($rows[0]['name'], $result[0]->name);
        $this->assertSame($rows[1]['name'], $result[1]->name);
    }

    protected function getTestData()
    {
        return [
            [
                'name' => 'Max',
            ],
            [
                'name' => 'John',
            ],
        ];
    }

    public function testConnectionWithPrefix()
    {
        $prefix     = 'test_';
        $connection = $this->createConnection($prefix);

        $this->setupDemoTable($connection);

        $this->assertSame($prefix, $connection->getTablePrefix());

        $first      = $connection->table('demotable')->first();
        $rows       = $this->getTestData();

        $this->assertSame($rows[0]['name'], $first->name);
    }

    public function testFunctionWithNumbers()
    {
        $connection = $this->createConnection();

        $procedureName = 'add_two';

        $command = '
            CREATE OR REPLACE FUNCTION  add_two (p1 IN NUMBER) RETURN NUMBER IS
            BEGIN
                 RETURN p1 + 2;
            END;
        ';

        $connection->getPdo()->exec($command);

        $first    = 5;
        $bindings = [
            'p1' => $first,
        ];

        $result = $connection->executeFunction($procedureName, $bindings);

        $this->assertSame($first + 2, (int) $result);
    }

    /**
     * @param Oci8Connection $connection
     */
    private function setupDemoTable($connection): void
    {
        $connection->getSchemaBuilder()->dropIfExists('demotable');
        $connection->getSchemaBuilder()->create('demotable', function (Blueprint $table) {
            $table->string('name');
        });

        $connection->table('demotable')->insert($this->getTestData());
    }
}
