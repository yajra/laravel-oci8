<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class FunctionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_return_numbers_using_a_function()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();

        $procedureName = 'add_two';

        $command = 'CREATE OR REPLACE FUNCTION  add_two (p1 IN NUMBER) RETURN NUMBER IS
            BEGIN
                 RETURN p1 + 2;
            END;';

        $connection->getPdo()->exec($command);

        $first = 5;
        $bindings = [
            'p1' => $first,
        ];

        $result = $connection->executeFunction($procedureName, $bindings);

        $this->assertSame($first + 2, (int) $result);
    }
}
