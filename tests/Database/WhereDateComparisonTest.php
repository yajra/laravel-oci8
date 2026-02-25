<?php

namespace Yajra\Oci8\Tests\Database;

use Carbon\Carbon;
use DateTime;
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

    // -------------------------------------------------------------------------
    // Date-only string inputs (YYYY-MM-DD)
    // For these, no time component is present. Oracle's TO_DATE with 'YYYY-MM-DD'
    // yields midnight, so TRUNC on the RHS is unnecessary.
    // -------------------------------------------------------------------------

    /**
     * Test whereDate with equals operator and a date-only string.
     */
    public function test_where_date_with_equals_operator_date_only_string()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") = to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with greater than operator and a date-only string.
     */
    public function test_where_date_with_greater_than_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") > to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with less than operator and a date-only string.
     */
    public function test_where_date_with_less_than_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '<', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") < to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with greater than or equal operator and a date-only string.
     */
    public function test_where_date_with_greater_than_or_equal_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") >= to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with less than or equal operator and a date-only string.
     */
    public function test_where_date_with_less_than_or_equal_operator()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '<=', '2015-12-21');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") <= to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with multiple date-only comparisons in the same query.
     */
    public function test_where_date_with_multiple_comparisons()
    {
        $this->builder->select('*')
            ->from('users')
            ->whereDate('created_at', '>=', '2015-12-21')
            ->whereDate('created_at', '<', '2015-12-31');

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertStringContainsString("trunc(\"CREATED_AT\") >= to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertStringContainsString("trunc(\"CREATED_AT\") < to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21', 1 => '2015-12-31'], $bindings);
    }

    // -------------------------------------------------------------------------
    // DateTimeInterface inputs (Carbon / DateTime)
    // Laravel's Builder::whereDate() normalises any DateTimeInterface to a
    // 'Y-m-d' string before the grammar is called, so the grammar always
    // receives a date-only string and uses the YYYY-MM-DD mask.
    // -------------------------------------------------------------------------

    /**
     * Test whereDate with a Carbon instance.
     *
     * Laravel's Builder::whereDate normalises any DateTimeInterface to 'Y-m-d' before
     * the grammar ever sees the value, so the grammar always receives a date-only string
     * and correctly uses the YYYY-MM-DD mask (no TRUNC on the RHS). The binding stored
     * on the builder is the normalised date string, not the original Carbon object.
     */
    public function test_where_date_with_carbon_instance()
    {
        $date = Carbon::create(2015, 12, 21, 10, 30, 0);

        $this->builder->select('*')->from('users')->whereDate('created_at', '=', $date);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") = to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with a Carbon instance and greater-than operator.
     *
     * Same normalisation applies: Carbon → 'Y-m-d' string before grammar is invoked.
     */
    public function test_where_date_with_carbon_instance_greater_than()
    {
        $date = Carbon::create(2015, 12, 21, 0, 0, 0);

        $this->builder->select('*')->from('users')->whereDate('created_at', '>', $date);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") > to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with a native PHP DateTime instance.
     *
     * Laravel's Builder::whereDate normalises DateTimeInterface to 'Y-m-d' before the
     * grammar sees it, so the binding is always a date-only string and the YYYY-MM-DD
     * mask is used (no TRUNC on the RHS).
     */
    public function test_where_date_with_datetime_instance()
    {
        $date = new DateTime('2015-12-21 10:30:00');

        $this->builder->select('*')->from('users')->whereDate('created_at', '=', $date);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") = to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-21'], $bindings);
    }

    /**
     * Test whereDate with a native PHP DateTime instance and less-than operator.
     */
    public function test_where_date_with_datetime_instance_less_than()
    {
        $date = new DateTime('2015-12-31 23:59:59');

        $this->builder->select('*')->from('users')->whereDate('created_at', '<', $date);

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame("select * from \"USERS\" where trunc(\"CREATED_AT\") < to_date(?, 'YYYY-MM-DD')", $sql);
        $this->assertEquals([0 => '2015-12-31'], $bindings);
    }

    // -------------------------------------------------------------------------
    // Raw Expression inputs
    // Raw expressions are passed through as-is; no TO_DATE or TRUNC wrapping.
    // -------------------------------------------------------------------------

    /**
     * Test whereDate with a Raw expression (should NOT convert/truncate the expression).
     */
    public function test_where_date_with_greater_than_and_raw_expression()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '>', new Expression('SYSDATE'));

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") > SYSDATE', $sql);
        $this->assertEquals([], $bindings);
    }

    /**
     * Test whereDate with equals and a Raw expression.
     */
    public function test_where_date_with_equals_and_raw_expression()
    {
        $this->builder->select('*')->from('users')->whereDate('created_at', '=', new Expression('TRUNC(SYSDATE)'));

        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();

        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = TRUNC(SYSDATE)', $sql);
        $this->assertEquals([], $bindings);
    }
}
