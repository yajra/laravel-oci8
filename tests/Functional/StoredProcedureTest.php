<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PDO;
use Yajra\Oci8\Tests\TestCase;

class StoredProcedureTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_return_sys_refcursor()
    {
        $connection = $this->getConnection();

        $command = 'CREATE OR REPLACE PROCEDURE sp_demo(p1 OUT SYS_REFCURSOR) AS
            BEGIN
                OPEN p1
                FOR
                SELECT name
                FROM users;
            END;';

        $connection->getPdo()->exec($command);

        $result = $connection->executeProcedureWithCursor('sp_demo');

        $this->assertSame('Record-1', $result[0]->name);
        $this->assertSame('Record-2', $result[1]->name);
        $this->assertSame('Record-20', $result[19]->name);
    }

    /** @test */
    public function it_can_work_with_numbers()
    {
        $connection = $this->getConnection();

        $procedureName = 'sp_demo';

        $command = '
            CREATE OR REPLACE PROCEDURE sp_demo(p1 IN NUMBER, p2 OUT NUMBER) AS
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

    /** @test */
    public function it_can_work_with_strings()
    {
        $connection = $this->getConnection();

        $procedureName = 'sp_demo';

        $command = '
            CREATE OR REPLACE PROCEDURE sp_demo(p1 IN VARCHAR2, p2 IN VARCHAR2, p3 OUT VARCHAR2) AS
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
}
