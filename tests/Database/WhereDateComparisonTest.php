<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Query\Grammars\OracleGrammar as QueryGrammar;

class WhereDateComparisonTest extends TestCase
{
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock connection for testing
        $connection = $this->createMock(\Yajra\Oci8\Oci8Connection::class);
        $connection->method('getSchemaPrefix')->willReturn('');
        $connection->method('getMaxLength')->willReturn(30);
        $connection->method('getDateFormat')->willReturn('YYYY-MM-DD HH24:MI:SS');

        $grammar = new QueryGrammar($connection);
        $this->builder = new Builder($connection, $grammar);
    }

    /**
     * Test whereDate with greater than operator properly converts and truncates the value
     */
    public function test_where_date_with_greater_than_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        // The SQL should use TO_DATE to convert string to date, then truncate both sides
        $this->assertStringContainsString('trunc("CREATED_AT")', $sql);
        $this->assertStringContainsString('>', $sql);
        $this->assertStringContainsString('trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);

        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with less than operator properly converts and truncates the value
     */
    public function test_where_date_with_less_than_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '<', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        // The SQL should use TO_DATE to convert string to date, then truncate both sides
        $this->assertStringContainsString('trunc("CREATED_AT")', $sql);
        $this->assertStringContainsString('<', $sql);
        $this->assertStringContainsString('trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);

        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with greater than or equal operator
     */
    public function test_where_date_with_greater_than_or_equal_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertStringContainsString('trunc("CREATED_AT")', $sql);
        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);

        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with less than or equal operator
     */
    public function test_where_date_with_less_than_or_equal_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '<=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertStringContainsString('trunc("CREATED_AT")', $sql);
        $this->assertStringContainsString('<=', $sql);
        $this->assertStringContainsString('trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);

        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with equals operator (truncates value with TO_DATE for consistency)
     */
    public function test_where_date_with_equals_operator_no_extra_truncate()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        // Now we convert and truncate the value for all operators to maintain consistency
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with greater than and Raw expression (should NOT convert/truncate the expression)
     */
    public function test_where_date_with_greater_than_and_raw_expression()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>', new Expression('SYSDATE'));

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        // Raw expressions should not be wrapped in TO_DATE or trunc
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") > SYSDATE', $sql);
        $this->assertEquals([], $bindings);
    }

    /**
     * Test whereDate with multiple comparison operators in same query
     */
    public function test_where_date_with_multiple_comparisons()
    {
        $this->builder->select('*')
            ->from('users')
            ->whereDate('created_at', '>=', '2015-12-21')
            ->whereDate('created_at', '<', '2015-12-31');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertStringContainsString('trunc("CREATED_AT") >= trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);
        $this->assertStringContainsString('trunc("CREATED_AT") < trunc(to_date(?, \'YYYY-MM-DD HH24:MI:SS\'))', $sql);
        $this->assertEquals([0 => '2015-12-21', 1 => '2015-12-31'], $bindings);
    }
}
