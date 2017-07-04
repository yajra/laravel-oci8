<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Oci8\Oci8Connection;

/**
 * Class Oci8IntegrationTest
 * This is a simple testcase using a local oracle instance via docker: https://github.com/wnameless/docker-oracle-xe-11g
 */
class Oci8IntegrationTest extends PHPUnit_Framework_TestCase
{
    public function testWorkingDatabaseConnection()
    {
        $capsule = $this->createConnectionCapsule();

        $this->assertInstanceOf(Connection::class, $capsule->getConnection());
    }

    public function testOracleReservedWordsInSchema()
    {
        //prepare connection
        $capsule = $this->createConnectionCapsule();
        $connection = $capsule->getConnection();
        $table = 'test';

        /*
         * Create a schema with reserved words as column names
         *
         * SQL> DESCRIBE test;
         * Name					   Null?    Type
         * -----------------------------------------------
         * ID					   NOT NULL NUMBER(10)
         * password				   NOT NULL VARCHAR2(255)
         * archivelog			   NOT NULL VARCHAR2(255)
         * columns				   NOT NULL VARCHAR2(255)
         */
        $connection->getSchemaBuilder()->create($table, function (Blueprint $table) {
            $table->increments('id');
            $table->string('password');
            $table->string('archivelog');
            $table->string('COLUMNS');
        });

        $connection->getSchemaBuilder()->getConnection()->table($table)->insert([
            'Password'   => str_random(20),
            'archivelog' => str_random(20),
            'COLUMNS'    => str_random(20)
        ]);

        $this->assertTrue($connection->getSchemaBuilder()->getConnection()->table($table)->exists());

        //remove table
        $connection->getSchemaBuilder()->drop($table);
    }

    /**
     * @return Capsule
     */
    protected function createConnectionCapsule()
    {
        $capsule = new Capsule;

        $manager = $capsule->getDatabaseManager();
        $manager->extend('oracle', function ($config) {
            $connector = new OracleConnector();
            $connection = $connector->connect($config);
            $db = new Oci8Connection($connection, $config["database"], $config["prefix"]);

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
            'driver'   => 'oracle',
            'host'     => 'localhost',
            'database' => 'xe',
            'username' => 'system',
            'password' => 'oracle',
            'prefix'   => '',
            'port'     => 49161
        ]);

        $capsule->bootEloquent();

        return $capsule;
    }
}
