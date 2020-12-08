<?php

namespace Yajra\Oci8\Tests\Database;

use BadMethodCallException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Expression as Raw;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Yajra\Oci8\Query\Processors\OracleProcessor;
use Yajra\Pdo\Oci8\Exceptions\Oci8Exception;

class Oci8QueryBuilderTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
    }

    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "USERS"', $builder->toSql());
    }

    public function testBasicSelectWithGetColumns()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()->shouldReceive('processSelect');
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertSame('select * from "USERS"', $sql);
        });
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertSame('select "FOO", "BAR" from "USERS"', $sql);
        });
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertSame('select "BAZ" from "USERS"', $sql);
        });

        $builder->from('users')->get();
        $this->assertNull($builder->columns);

        $builder->from('users')->get(['foo', 'bar']);
        $this->assertNull($builder->columns);

        $builder->from('users')->get('baz');
        $this->assertNull($builder->columns);

        $this->assertSame('select * from "USERS"', $builder->toSql());
        $this->assertNull($builder->columns);
    }

    public function testBasicSelectWithReservedWords()
    {
        $builder = $this->getBuilder();
        $builder->select('exists', 'drop', 'group')->from('users');
        $this->assertEquals('select "EXISTS", "DROP", "GROUP" from "USERS"', $builder->toSql());
    }

    public function testBasicSelectUseWritePdo()
    {
        $builder = $this->getBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()
            ->with('select * from "USERS"', [], false);
        $builder->useWritePdo()->select('*')->from('users')->get();

        $builder = $this->getBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()
            ->with('select * from "USERS"', [], true);
        $builder->select('*')->from('users')->get();
    }

    public function testBasicTableWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertSame('select * from "SOME""TABLE"', $builder->toSql());
    }

    public function testAliasWrappingAsWholeConstant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertSame('select "X"."Y" as "FOO.BAR" from "BAZ"', $builder->toSql());
    }

    /**
     * @TODO: Correct output should also wrap x.
     *          select "W" "X"."Y"."Z" as "FOO.BAR" from "BAZ"
     */
    public function testAliasWrappingWithSpacesInDatabaseName()
    {
        $builder = $this->getBuilder();
        $builder->select('w x.y.z as foo.bar')->from('baz');
        $this->assertSame('select "W" x."Y"."Z" as "FOO.BAR" from "BAZ"', $builder->toSql());
    }

    public function testAddingSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select "FOO", "BAR", "BAZ", "BOOM" from "USERS"', $builder->toSql());
    }

    public function testBasicSelectWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "PREFIX_USERS"', $builder->toSql());
    }

    public function testBasicSelectDistinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select('foo', 'bar')->from('users');
        $this->assertEquals('select distinct "FOO", "BAR" from "USERS"', $builder->toSql());
    }

    public function testBasicSelectDistinctOnColumns()
    {
        $builder = $this->getBuilder();
        $builder->distinct('foo')->select('foo', 'bar')->from('users');
        $this->assertSame('select distinct "FOO", "BAR" from "USERS"', $builder->toSql());
    }

    public function testBasicAlias()
    {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select "FOO" as "BAR" from "USERS"', $builder->toSql());
    }

    /**
     * @TODO: Fix alias prefix and wrapping.
     *      select * from "PREFIX_USERS" "PREFIX_PEOPLE"
     */
    public function testAliasWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users as people');
        $this->assertSame('select * from "PREFIX_USERS" people', $builder->toSql());
    }

    /**
     * @TODO: Fix alias prefix
     *      select * from "PREFIX_SERVICES" inner join "PREFIX_TRANSLATIONS" "PREFIX_T" on "PREFIX_T"."ITEM_ID" = "PREFIX_SERVICES"."ID"
     */
    public function testJoinAliasesWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertSame('select * from "PREFIX_SERVICES" inner join "PREFIX_TRANSLATIONS" t on "PREFIX_T"."ITEM_ID" = "PREFIX_SERVICES"."ID"', $builder->toSql());
    }

    public function testBasicTableWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertSame('select * from "PUBLIC"."USERS"', $builder->toSql());
    }

    public function testWhenCallback()
    {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithReturn()
    {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithDefault()
    {
        $callback = function ($query, $condition) {
            $this->assertEquals('truthy', $condition);

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertEquals(0, $condition);

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when('truthy', $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(0, $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testUnlessCallback()
    {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithReturn()
    {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithDefault()
    {
        $callback = function ($query, $condition) {
            $this->assertEquals(0, $condition);

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertEquals('truthy', $condition);

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(0, $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless('truthy', $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testTapCallback()
    {
        $callback = function ($query) {
            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->tap($callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
    }

    public function testBasicSchemaWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('acme.users');
        $this->assertEquals('select * from "ACME"."USERS"', $builder->toSql());
    }

    public function testBasicSchemaWrappingReservedWords()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('schema.users');
        $this->assertEquals('select * from "SCHEMA"."USERS"', $builder->toSql());
    }

    public function testBasicColumnWrappingReservedWords()
    {
        $builder = $this->getBuilder();
        $builder->select('order')->from('users');
        $this->assertEquals('select "ORDER" from "USERS"', $builder->toSql());
    }

    public function testBasicWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWheresWithReservedWords()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('blob', '=', 1);
        $this->assertEquals('select * from "USERS" where "BLOB" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWheresWithArrayValue()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 12, 1 => 30], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 12, 1 => 30], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '!=', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" != ?', $builder->toSql());
        $this->assertEquals([0 => 12, 1 => 30], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '<>', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" <> ?', $builder->toSql());
        $this->assertEquals([0 => 12, 1 => 30], $builder->getBindings());
    }

    public function testDateBasedWheresAcceptsTwoArguments()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', 1);
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', 1);
        $this->assertSame('select * from "USERS" where extract (day from "CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', 1);
        $this->assertSame('select * from "USERS" where extract (month from "CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', 1);
        $this->assertSame('select * from "USERS" where extract (year from "CREATED_AT") = ?', $builder->toSql());
    }

    public function testDateBasedOrWheresAcceptsTwoArguments()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDate('created_at', 1);
        $this->assertSame('select * from "USERS" where "ID" = ? or trunc("CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDay('CREATED_AT', 1);
        $this->assertSame('select * from "USERS" where "ID" = ? or extract (day from "CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereMonth('CREATED_AT', 1);
        $this->assertSame('select * from "USERS" where "ID" = ? or extract (month from "CREATED_AT") = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereYear('created_at', 1);
        $this->assertSame('select * from "USERS" where "ID" = ? or extract (year from "CREATED_AT") = ?', $builder->toSql());
    }

    public function testDateBasedWheresExpressionIsNotBound()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'))->where('admin', true);
        $this->assertEquals([true], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', new Raw('NOW()'));
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', new Raw('NOW()'));
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', new Raw('NOW()'));
        $this->assertEquals([], $builder->getBindings());
    }

    public function testWhereDate()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', new Raw('NOW()'));
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = NOW()', $builder->toSql());
    }

    public function testWhereDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 20);
        $this->assertEquals('select * from "USERS" where extract (day from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 20], $builder->getBindings());
    }

    public function testOrWhereDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertSame('select * from "USERS" where extract (day from "CREATED_AT") = ? or extract (day from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testWhereMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 12);
        $this->assertEquals('select * from "USERS" where extract (month from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());
    }

    public function testOrWhereMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertSame('select * from "USERS" where extract (month from "CREATED_AT") = ? or extract (month from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function testWhereYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2015);
        $this->assertEquals('select * from "USERS" where extract (year from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 2015], $builder->getBindings());
    }

    public function testOrWhereYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertSame('select * from "USERS" where extract (year from "CREATED_AT") = ? or extract (year from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function testWhereTime()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from "USERS" where extract (time from "CREATED_AT") >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeOperatorOptional()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from "USERS" where extract (time from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereLike()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 'like', '1');
        $this->assertSame('select * from "USERS" where "ID" like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 'LIKE', '1');
        $this->assertSame('select * from "USERS" where "ID" LIKE ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 'ilike', '1');
        $this->assertSame('select * from "USERS" where "ID" ilike ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 'not like', '1');
        $this->assertSame('select * from "USERS" where "ID" not like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 'not ilike', '1');
        $this->assertSame('select * from "USERS" where "ID" not ilike ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());
    }

    public function testWhereBetweens()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertSame('select * from "USERS" where "ID" between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
        $this->assertSame('select * from "USERS" where "ID" not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [new Raw(1), new Raw(2)]);
        $this->assertSame('select * from "USERS" where "ID" between 1 and 2', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testWhereBetweenColumns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetweenColumns('id', ['users.created_at', 'users.updated_at']);
        $this->assertSame('select * from "USERS" where "ID" between "USERS"."CREATED_AT" and "USERS"."UPDATED_AT"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetweenColumns('id', ['created_at', 'updated_at']);
        $this->assertSame('select * from "USERS" where "ID" not between "CREATED_AT" and "UPDATED_AT"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetweenColumns('id', [new Raw(1), new Raw(2)]);
        $this->assertSame('select * from "USERS" where "ID" between 1 and 2', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testBasicOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
        $this->assertEquals('select * from "USERS" where id = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
        $this->assertEquals('select * from "USERS" where "ID" = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "USERS" where "ID" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "USERS" where "ID" = ? or "ID" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testBasicWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "USERS" where "ID" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "USERS" where "ID" = ? or "ID" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testRawWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [new Raw(1)]);
        $this->assertSame('select * from "USERS" where "ID" in (1)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new Raw(1)]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" in (1)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', []);
        $this->assertSame('select * from "USERS" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', []);
        $this->assertSame('select * from "USERS" where "ID" = ? or 0 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', []);
        $this->assertSame('select * from "USERS" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', []);
        $this->assertSame('select * from "USERS" where "ID" = ? or 1 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereIntegerInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testOrWhereIntegerInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereIntegerNotInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" not in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testOrWhereIntegerNotInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" not in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereIntegerInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', []);
        $this->assertSame('select * from "USERS" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testEmptyWhereIntegerNotInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', []);
        $this->assertSame('select * from "USERS" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testBasicWhereColumn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn('first_name', 'last_name')->orWhereColumn('first_name', 'middle_name');
        $this->assertSame('select * from "USERS" where "FIRST_NAME" = "LAST_NAME" or "FIRST_NAME" = "MIDDLE_NAME"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn('updated_at', '>', 'created_at');
        $this->assertSame('select * from "USERS" where "UPDATED_AT" > "CREATED_AT"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testArrayWhereColumn()
    {
        $conditions = [
            ['first_name', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ];

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn($conditions);
        $this->assertSame('select * from "USERS" where ("FIRST_NAME" = "LAST_NAME" and "UPDATED_AT" > "CREATED_AT")', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertSame('(select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMultipleUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testMultipleUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testUnionOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?) order by "ID" desc',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    /**
     * @TODO: Fix union sql with limit
     */
    public function testUnionLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        // $this->assertSame('(select * from "USERS") union (select * from "DOGS") limit 10 offset 5', $builder->toSql());
        $this->assertSame('(select * from "USERS") union (select * from "DOGS")  ', $builder->toSql());

        $builder = $this->getBuilder();
        // $expectedSql = '(select "A" from "T1" where "A" = ? and "B" = ?) union (select "A" from "T2" where "A" = ? and "B" = ?) order by "A" asc limit 10';
        $expectedSql = '(select "A" from "T1" where "A" = ? and "B" = ?) union (select "A" from "T2" where "A" = ? and "B" = ?) order by "A" asc ';
        $union       = $this->getBuilder()->select('a')->from('t2')->where('a', 11)->where('b', 2);
        $builder->select('a')->from('t1')->where('a', 10)->where('b', 1)->union($union)->orderBy('a')->limit(10);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([0 => 10, 1 => 1, 2 => 11, 3 => 2], $builder->getBindings());
    }

    public function testUnionWithJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs')->join('breeds', function ($join) {
            $join->on('dogs.breed_id', '=', 'breeds.id')
                ->where('breeds.is_native', '=', 1);
        }));
        $this->assertSame('(select * from "USERS") union (select * from "DOGS" inner join "BREEDS" on "DOGS"."BREED_ID" = "BREEDS"."ID" and "BREEDS"."IS_NATIVE" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testUnionAggregate()
    {
        $expected = 'select count(*) as aggregate from ((select * from "POSTS") union (select * from "VIDEOS")) "TEMP_TABLE"';
        $builder  = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
        $builder->getProcessor()->shouldReceive('processSelect')->once();
        $builder->from('posts')->union($this->getBuilder()->from('videos'))->count();
    }

    public function testBasicWhereInThousands()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', range(1, 1001));
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" in (%s) or "ID" in (?))',
            substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(range(1, 1001), $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', range(1, 1001))->where('id', 1);
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" in (%s) or "ID" in (?)) and "ID" = ?',
            substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
    }

    public function testBasicWhereNotInThousands()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', range(1, 1001));
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" not in (%s) and "ID" not in (?))',
            substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(range(1, 1001), $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', range(1, 1001))->where('id', 1);
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" not in (%s) and "ID" not in (?)) and "ID" = ?',
            substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
    }

    public function testSubSelectWhereIns()
    {
        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals(
            'select * from "USERS" where "ID" in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 where rownum <= 3) t2 where t2."rn" >= 1)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals(
            'select * from "USERS" where "ID" not in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 where rownum <= 3) t2 where t2."rn" >= 1)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());
    }

    public function testBasicWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertEquals('select * from "USERS" where "ID" is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "ID" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testArrayWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull(['id', 'expires_at']);
        $this->assertSame('select * from "USERS" where "ID" is null and "EXPIRES_AT" is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull(['id', 'expires_at']);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" is null or "EXPIRES_AT" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');
        $this->assertEquals('select * from "USERS" where "ID" is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
        $this->assertEquals('select * from "USERS" where "ID" > ? or "ID" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testArrayWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "USERS" where "ID" is not null and "EXPIRES_AT" is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "USERS" where "ID" > ? or "ID" is not null or "EXPIRES_AT" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testGroupBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email');
        $this->assertSame('select * from "USERS" group by "EMAIL"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertSame('select * from "USERS" group by "ID", "EMAIL"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(['id', 'email']);
        $this->assertSame('select * from "USERS" group by "ID", "EMAIL"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(new Raw('DATE(created_at)'));
        $this->assertSame('select * from "USERS" group by DATE(created_at)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupByRaw('DATE(created_at), ? DESC', ['foo']);
        $this->assertSame('select * from "USERS" group by DATE(created_at), ? DESC', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->havingRaw('?', ['havingRawBinding'])->groupByRaw('?', ['groupByRawBinding'])->whereRaw('?', ['whereRawBinding']);
        $this->assertEquals(['whereRawBinding', 'groupByRawBinding', 'havingRawBinding'], $builder->getBindings());
    }

    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertSame('select * from "USERS" order by "EMAIL" asc, "AGE" desc', $builder->toSql());

        $builder->orders = null;
        $this->assertSame('select * from "USERS"', $builder->toSql());

        $builder->orders = [];
        $this->assertSame('select * from "USERS"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('"age" ? desc', ['foo']);
        $this->assertSame('select * from "USERS" order by "EMAIL" asc, "age" ? desc', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByDesc('name');
        $this->assertSame('select * from "USERS" order by "NAME" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('posts')->where('public', 1)
            ->unionAll($this->getBuilder()->select('*')->from('videos')->where('public', 1))
            ->orderByRaw('field(category, ?, ?) asc', ['news', 'opinion']);
        $this->assertSame('(select * from "POSTS" where "PUBLIC" = ?) union all (select * from "VIDEOS" where "PUBLIC" = ?) order by field(category, ?, ?) asc', $builder->toSql());
        $this->assertEquals([1, 1, 'news', 'opinion'], $builder->getBindings());
    }

    public function testReorder()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name');
        $this->assertSame('select * from "USERS" order by "NAME" asc', $builder->toSql());
        $builder->reorder();
        $this->assertSame('select * from "USERS"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name');
        $this->assertSame('select * from "USERS" order by "NAME" asc', $builder->toSql());
        $builder->reorder('email', 'desc');
        $this->assertSame('select * from "USERS" order by "EMAIL" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('first');
        $builder->union($this->getBuilder()->select('*')->from('second'));
        $builder->orderBy('name');
        $this->assertSame('(select * from "FIRST") union (select * from "SECOND") order by "NAME" asc', $builder->toSql());
        $builder->reorder();
        $this->assertSame('(select * from "FIRST") union (select * from "SECOND")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByRaw('?', [true]);
        $this->assertEquals([true], $builder->getBindings());
        $builder->reorder();
        $this->assertEquals([], $builder->getBindings());
    }

    public function testOrderBySubQueries()
    {
        $expected = 'select * from "USERS" order by (select * from (select "CREATED_AT" from "LOGINS" where "USER_ID" = "USERS"."ID") where rownum = 1)';
        $subQuery = function ($query) {
            return $query->select('created_at')->from('logins')->whereColumn('user_id', 'users.id')->limit(1);
        };

        $builder = $this->getBuilder()->select('*')->from('users')->orderBy($subQuery);
        $this->assertSame("$expected asc", $builder->toSql());

        $builder = $this->getBuilder()->select('*')->from('users')->orderBy($subQuery, 'desc');
        $this->assertSame("$expected desc", $builder->toSql());

        $builder = $this->getBuilder()->select('*')->from('users')->orderByDesc($subQuery);
        $this->assertSame("$expected desc", $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('posts')->where('public', 1)
            ->unionAll($this->getBuilder()->select('*')->from('videos')->where('public', 1))
            ->orderBy($this->getBuilder()->selectRaw('field(category, ?, ?)', ['news', 'opinion']));
        $this->assertSame('(select * from "POSTS" where "PUBLIC" = ?) union all (select * from "VIDEOS" where "PUBLIC" = ?) order by (select field(category, ?, ?)) asc', $builder->toSql());
        $this->assertEquals([1, 1, 'news', 'opinion'], $builder->getBindings());
    }

    public function testOrderByInvalidDirectionParam()
    {
        $this->expectException(InvalidArgumentException::class);

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('age', 'asec');
    }

    public function testHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', '>', 1);
        $this->assertEquals('select * from "USERS" having "EMAIL" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email')->having('email', '>', 1);
        $this->assertEquals('select * from "USERS" group by "EMAIL" having "EMAIL" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('email as foo_email')->from('users')->having('foo_email', '>', 1);
        $this->assertEquals('select "EMAIL" as "FOO_EMAIL" from "USERS" having "FOO_EMAIL" > ?', $builder->toSql());
    }

    public function testHavingShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', 1)->orHaving('email', 2);
        $this->assertSame('select * from "USERS" having "EMAIL" = ? or "EMAIL" = ?', $builder->toSql());
    }

    public function testHavingFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $query   = 'select "CATEGORY", count(*) as "TOTAL" from "ITEM" where "DEPARTMENT" = ? group by "CATEGORY" having "TOTAL" > ?';
        $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular', 3], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "TOTAL"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3)->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());

        // Using \Raw value
        $builder = $this->getBuilder();
        $query   = 'select "CATEGORY", count(*) as "TOTAL" from "ITEM" where "DEPARTMENT" = ? group by "CATEGORY" having "TOTAL" > 3';
        $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular'], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "TOTAL"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'))->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());
    }

    public function testRawHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "USERS" having user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "USERS" having "BAZ" = ? or user_foo < user_bar', $builder->toSql());
    }

    public function testOffset()
    {
        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->offset(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 10) t2 where t2."rn" >= 11',
            $builder->toSql()
        );
    }

    public function testLimitsAndOffsets()
    {
        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 15) t2 where t2."rn" >= 6',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->skip(5)->take(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 15) t2 where t2."rn" >= 6',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->skip(-5)->take(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 10) t2 where t2."rn" >= 1',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 30) t2 where t2."rn" >= 16',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 15) t2 where t2."rn" >= 1',
            $builder->toSql()
        );
    }

    public function testLimitAndOffsetToPaginateOne()
    {
        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->offset(0)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 1) t2 where t2."rn" >= 1',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $builder->select('*')->from('users')->offset(1)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 2) t2 where t2."rn" >= 2',
            $builder->toSql()
        );
    }

    /**
     * @TODO: Review for page
     */
    public function testForPage()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertSame('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 30) t2 where t2."rn" >= 16', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 15);
        $this->assertSame('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 15) t2 where t2."rn" >= 1', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertSame('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 where rownum <= 15) t2 where t2."rn" >= 1', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 0);
        // $this->assertSame('select * from "USERS" limit 0 offset 0', $builder->toSql());
        $this->assertSame('select * from "USERS"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 0);
        // $this->assertSame('select * from "USERS" limit 0 offset 0', $builder->toSql());
        $this->assertSame('select * from "USERS"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 0);
        // $this->assertSame('select * from "USERS" limit 0 offset 0', $builder->toSql());
        $this->assertSame('select * from "USERS"', $builder->toSql());
    }

    public function testGetCountForPaginationWithBindings()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->selectSub(function ($q) {
            $q->select('body')->from('posts')->where('id', 4);
        }, 'post');

        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
        $this->assertEquals([4], $builder->getBindings());
    }

    public function testGetCountForPaginationWithColumnAliases()
    {
        $builder = $this->getBuilder();
        $columns = ['body as post_body', 'teaser', 'posts.created as published'];
        $builder->from('posts')->select($columns);

        $builder->getConnection()->shouldReceive('select')->once()->with('select count("BODY", "TEASER", "POSTS"."CREATED") as aggregate from "POSTS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination($columns);
        $this->assertEquals(1, $count);
    }

    public function testGetCountForPaginationWithUnion()
    {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'));

        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from ((select "ID" from "POSTS") union (select "ID" from "VIDEOS")) "TEMP_TABLE"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function testWhereShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "NAME" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testWhereWithArrayConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]]);
        $this->assertSame('select * from "USERS" where ("FOO" = ? and "BAR" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
        $this->assertSame('select * from "USERS" where ("FOO" = ? and "BAR" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]]);
        $this->assertSame('select * from "USERS" where ("FOO" = ? and "BAR" < ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testNestedWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        $this->assertEquals('select * from "USERS" where "EMAIL" = ? or ("NAME" = ? and "AGE" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }

    public function testNestedWhereBindings()
    {
        $builder = $this->getBuilder();
        $builder->where('email', '=', 'foo')->where(function ($q) {
            $q->selectRaw('?', ['ignore'])->where('name', '=', 'bar');
        });
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testFullSubSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function ($q) {
            $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
        });

        $this->assertEquals('select * from "USERS" where "EMAIL" = ? or "ID" = (select max(id) from "USERS" where "EMAIL" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testWhereExists()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
        });
        $this->assertEquals('select * from "ORDERS" where exists (select * from "PRODUCTS" where "PRODUCTS"."ID" = orders.id)',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
        });
        $this->assertEquals('select * from "ORDERS" where not exists (select * from "PRODUCTS" where "PRODUCTS"."ID" = orders.id)',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
        });
        $this->assertEquals('select * from "ORDERS" where "ID" = ? or exists (select * from "PRODUCTS" where "PRODUCTS"."ID" = orders.id)',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
        });
        $this->assertEquals('select * from "ORDERS" where "ID" = ? or not exists (select * from "PRODUCTS" where "PRODUCTS"."ID" = orders.id)',
            $builder->toSql());
    }

    public function testBasicJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
                ->from('users')
                ->join('contacts', 'users.id', '=', 'contacts.id')
                ->leftJoin('photos', 'users.id', '=', 'photos.id');
        $this->assertEquals('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" left join "PHOTOS" on "USERS"."ID" = "PHOTOS"."ID"',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')
                ->from('users')
                ->leftJoinWhere('photos', 'users.id', '=', 'bar')
                ->joinWhere('photos', 'users.id', '=', 'foo');
        $this->assertEquals('select * from "USERS" left join "PHOTOS" on "USERS"."ID" = ? inner join "PHOTOS" on "USERS"."ID" = ?',
            $builder->toSql());
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }

    public function testCrossJoinSubs()
    {
        $builder = $this->getBuilder();
        $builder->selectRaw('(sale / overall.sales) * 100 AS percent_of_total')->from('sales')->crossJoinSub($this->getBuilder()->selectRaw('SUM(sale) AS sales')->from('sales'), 'overall');
        $this->assertSame('select (sale / overall.sales) * 100 AS percent_of_total from "SALES" cross join (select SUM(sale) AS sales from "SALES") "OVERALL"', $builder->toSql());
    }

    public function testComplexJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
        });
        $this->assertEquals('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "USERS"."NAME" = "CONTACTS"."NAME"',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->where('users.id', '=', 'foo')->orWhere('users.name', '=', 'bar');
        });
        $this->assertEquals('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = ? or "USERS"."NAME" = ?',
            $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testJoinWhereNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."DELETED_AT" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "CONTACTS"."DELETED_AT" is null', $builder->toSql());
    }

    public function testJoinWhereNotNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."DELETED_AT" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "CONTACTS"."DELETED_AT" is not null', $builder->toSql());
    }

    public function testJoinWhereIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."NAME" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "CONTACTS"."NAME" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinWhereInSubquery()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', $q);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."NAME" in (select "NAME" from "CONTACTS" where "NAME" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', $q);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "CONTACTS"."NAME" in (select "NAME" from "CONTACTS" where "NAME" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testJoinWhereNotIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."NAME" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" or "CONTACTS"."NAME" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinsWithNestedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where(function ($j) {
                $j->where('contacts.country', '=', 'US')->orWhere('contacts.is_partner', '=', 1);
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and ("CONTACTS"."COUNTRY" = ? or "CONTACTS"."IS_PARTNER" = ?)', $builder->toSql());
        $this->assertEquals(['US', 1], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where('contacts.is_active', '=', 1)->orOn(function ($j) {
                $j->orWhere(function ($j) {
                    $j->where('contacts.country', '=', 'UK')->orOn('contacts.type', '=', 'users.type');
                })->where(function ($j) {
                    $j->where('contacts.country', '=', 'US')->orWhereNull('contacts.is_partner');
                });
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACTS"."IS_ACTIVE" = ? or (("CONTACTS"."COUNTRY" = ? or "CONTACTS"."TYPE" = "USERS"."TYPE") and ("CONTACTS"."COUNTRY" = ? or "CONTACTS"."IS_PARTNER" is null))', $builder->toSql());
        $this->assertEquals([1, 'UK', 'US'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->where(function ($j) {
                $j->whereRole('admin')
                    ->orWhereNull('contacts.disabled')
                    ->orWhereRaw('year(contacts.created_at) = 2016');
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and ("ROLE" = ? or "CONTACTS"."DISABLED" is null or year(contacts.created_at) = 2016)', $builder->toSql());
        $this->assertEquals(['admin'], $builder->getBindings());
    }

    public function testJoinsWithSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereIn('contact_type_id', function ($q) {
                $q->select('id')->from('contact_types')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at');
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and "CONTACT_TYPE_ID" in (select "ID" from "CONTACT_TYPES" where "CATEGORY_ID" = ? and "DELETED_AT" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at');
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and exists (select 1 from "CONTACT_TYPES" where contact_types.id = contacts.contact_type_id and "CATEGORY_ID" = ? and "DELETED_AT" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at')
                    ->whereIn('level_id', function ($q) {
                        $q->select('id')->from('levels')
                            ->where('is_active', true);
                    });
            });
        });
        $this->assertSame('select * from "USERS" left join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" and exists (select 1 from "CONTACT_TYPES" where contact_types.id = contacts.contact_type_id and "CATEGORY_ID" = ? and "DELETED_AT" is null and "LEVEL_ID" in (select "ID" from "LEVELS" where "IS_ACTIVE" = ?))', $builder->toSql());
        $this->assertEquals(['1', true], $builder->getBindings());
    }

    public function testJoinsWithNestedJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
        });
        $this->assertSame('select "USERS"."ID", "CONTACTS"."ID", "CONTACT_TYPES"."ID" from "USERS" left join ("CONTACTS" inner join "CONTACT_TYPES" on "CONTACTS"."CONTACT_TYPE_ID" = "CONTACT_TYPES"."ID") on "USERS"."ID" = "CONTACTS"."ID"', $builder->toSql());
    }

    public function testJoinsWithMultipleNestedJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id', 'countrys.id', 'planets.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                ->leftJoin('countrys', function ($q) {
                    $q->on('contacts.country', '=', 'countrys.country')
                        ->join('planets', function ($q) {
                            $q->on('countrys.planet_id', '=', 'planet.id')
                                ->where('planet.is_settled', '=', 1)
                                ->where('planet.population', '>=', 10000);
                        });
                });
        });
        $this->assertSame('select "USERS"."ID", "CONTACTS"."ID", "CONTACT_TYPES"."ID", "COUNTRYS"."ID", "PLANETS"."ID" from "USERS" left join ("CONTACTS" inner join "CONTACT_TYPES" on "CONTACTS"."CONTACT_TYPE_ID" = "CONTACT_TYPES"."ID" left join ("COUNTRYS" inner join "PLANETS" on "COUNTRYS"."PLANET_ID" = "PLANET"."ID" and "PLANET"."IS_SETTLED" = ? and "PLANET"."POPULATION" >= ?) on "CONTACTS"."COUNTRY" = "COUNTRYS"."COUNTRY") on "USERS"."ID" = "CONTACTS"."ID"', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinsWithNestedJoinWithAdvancedSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                ->whereExists(function ($q) {
                    $q->select('*')->from('countrys')
                        ->whereColumn('contacts.country', '=', 'countrys.country')
                        ->join('planets', function ($q) {
                            $q->on('countrys.planet_id', '=', 'planet.id')
                                ->where('planet.is_settled', '=', 1);
                        })
                        ->where('planet.population', '>=', 10000);
                });
        });
        $this->assertSame('select "USERS"."ID", "CONTACTS"."ID", "CONTACT_TYPES"."ID" from "USERS" left join ("CONTACTS" inner join "CONTACT_TYPES" on "CONTACTS"."CONTACT_TYPE_ID" = "CONTACT_TYPES"."ID") on "USERS"."ID" = "CONTACTS"."ID" and exists (select * from "COUNTRYS" inner join "PLANETS" on "COUNTRYS"."PLANET_ID" = "PLANET"."ID" and "PLANET"."IS_SETTLED" = ? where "CONTACTS"."COUNTRY" = "COUNTRYS"."COUNTRY" and "PLANET"."POPULATION" >= ?)', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinSub()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->joinSub('select * from "CONTACTS"', 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" inner join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->from('users')->joinSub(function ($q) {
            $q->from('contacts');
        }, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" inner join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $builder         = $this->getBuilder();
        $eloquentBuilder = new EloquentBuilder($this->getBuilder()->from('contacts'));
        $eloquentBuilder->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->joinSub($eloquentBuilder, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" inner join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $builder = $this->getBuilder();
        $sub1    = $this->getBuilder()->from('contacts')->where('name', 'foo');
        $sub1->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $sub2 = $this->getBuilder()->from('contacts')->where('name', 'bar');
        $sub2->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')
                ->joinSub($sub1, 'sub1', 'users.id', '=', 1, 'inner', true)
                ->joinSub($sub2, 'sub2', 'users.id', '=', 'sub2.user_id');
        $expected = 'select * from "USERS" ';
        $expected .= 'inner join (select * from "CONTACTS" where "NAME" = ?) "SUB1" on "USERS"."ID" = ? ';
        $expected .= 'inner join (select * from "CONTACTS" where "NAME" = ?) "SUB2" on "USERS"."ID" = "SUB2"."USER_ID"';
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(['foo', 1, 'bar'], $builder->getRawBindings()['join']);

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('users')->joinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
    }

    public function testJoinSubWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->from('users')->joinSub('select * from "CONTACTS"', 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "PREFIX_USERS" inner join (select * from "CONTACTS") "PREFIX_SUB" on "PREFIX_USERS"."ID" = "PREFIX_SUB"."ID"', $builder->toSql());
    }

    public function testLeftJoinSub()
    {
        $builder = $this->getBuilder();
        $sub     = $this->getBuilder()->from('contacts');
        $sub->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->leftJoinSub($sub, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" left join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('users')->leftJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
    }

    public function testRightJoinSub()
    {
        $builder = $this->getBuilder();
        $sub     = $this->getBuilder()->from('contacts');
        $sub->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->rightJoinSub($sub, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" right join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('users')->rightJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
    }

    public function testRawExpressionsInSelect()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');
        $this->assertEquals('select substr(foo, 6) from "USERS"', $builder->toSql());
    }

    public function testFindReturnsFirstResultByID()
    {
        $builder    = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select * from (select * from "USERS" where "ID" = ?) where rownum = 1',
                    [1], true)
                ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['foo' => 'bar']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->find(1);
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstMethodReturnsFirstResult()
    {
        $builder    = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')
                   ->once()
                   ->with('select * from (select * from "USERS" where "ID" = ?) where rownum = 1',
                       [1], true)
                   ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['foo' => 'bar']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->where('id', '=', 1)->first();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testPluckMethodGetsCollectionOfColumnValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function testImplode()
    {
        // Test without glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
        $this->assertSame('barbaz', $results);

        // Test with glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
        $this->assertSame('bar,baz', $results);
    }

    public function testValueMethodReturnsSingleColumn()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select * from (select "FOO" from "USERS" where "ID" = ?) where rownum = 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturn([['foo' => 'bar']]);
        $results = $builder->from('users')->where('id', '=', 1)->value('foo');
        $this->assertSame('bar', $results);
    }

    public function testListMethodsGetsArrayOfColumnValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([
            ['id' => 1, 'foo' => 'bar'],
            ['id' => 10, 'foo' => 'baz'],
        ]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function testAggregateFunctions()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select 1 as "exists" from "USERS" where rownum = 1', [], true)->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select 1 as "exists" from "USERS" where rownum = 1', [], true)->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExist();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select max("ID") as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select min("ID") as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select sum("ID") as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function testExistsOr()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            throw new RuntimeException();
        });
        $this->assertTrue($results);
    }

    public function testDoesntExistsOr()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->existsOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->existsOr(function () {
            throw new RuntimeException();
        });
        $this->assertTrue($results);
    }

    public function testAggregateResetFollowedByGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select sum("ID") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 2]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "COLUMN1", "COLUMN2" from "USERS"', [], true)
            ->andReturn([['column1' => 'foo', 'column2' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->select('column1', 'column2');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $sum = $builder->sum('id');
        $this->assertEquals(2, $sum);
        $result = $builder->get();
        $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count("COLUMN1") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "COLUMN2", "COLUMN3" from "USERS"', [], true)
            ->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->select('column2', 'column3')->get();
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedByGetWithColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count("COLUMN1") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "COLUMN2", "COLUMN3" from "USERS"', [], true)
            ->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->get(['column2', 'column3']);
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateWithSubSelect()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->selectSub(function ($query) {
            $query->from('posts')->select('foo')->where('title', 'foo');
        }, 'post');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testSubQueriesBindings()
    {
        $builder = $this->getBuilder();
        $second  = $this->getBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
        $third   = $this->getBuilder()
            ->select('*')
            ->from('users')
            ->where('id', 3)
            ->groupBy('id')
            ->having('id', '!=', 4);
        $builder->groupBy('a')->having('a', '=', 1)->union($second)->union($third);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 3 => 4], $builder->getBindings());

        $builder = $this->getBuilder()->select('*')->from('users')->where('email', '=', function ($q) {
            $q->select(new Raw('max(id)'))
                ->from('users')->where('email', '=', 'bar')
                ->orderByRaw('email like ?', '%.com')
                ->groupBy('id')->having('id', '=', 4);
        })->orWhere('id', '=', 'foo')->groupBy('id')->having('id', '=', 5);
        $this->assertEquals([0 => 'bar', 1 => 4, 2 => '%.com', 3 => 'foo', 4 => 5], $builder->getBindings());
    }

    public function testAggregateCountFunction()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select count(*) as aggregate from "USERS"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);
    }

    public function testAggregateExistsFunction()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')
                ->once()
                ->with('select 1 as "exists" from "USERS" where rownum = 1', [], true)
                ->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);
    }

    public function testAggregateMaxFunction()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select max("ID") as aggregate from "USERS"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);
    }

    public function testAggregateMinFunction()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select min("ID") as aggregate from "USERS"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);
    }

    public function testAggregateSumFunction()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select sum("ID") as aggregate from "USERS"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function testInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('insert')
                ->once()
                ->with('insert into "USERS" ("EMAIL") values (?)', ['foo'])
                ->andReturn(true);
        $result = $builder->from('users')->insert(['email' => 'foo']);
        $this->assertTrue($result);
    }

    public function testInsertUsingMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "TABLE1" ("FOO") select "BAR" from "TABLE2" where "FOREIGN_ID" = ?', [5])->andReturn(1);

        $result = $builder->from('table1')->insertUsing(
            ['foo'],
            function (Builder $query) {
                $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
            }
        );

        $this->assertEquals(1, $result);
    }

    public function testInsertUsingInvalidSubquery()
    {
        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('table1')->insertUsing(['foo'], ['bar']);
    }

    public function testInsertOrIgnoreMethod()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support');
        $builder = $this->getBuilder();
        $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    }

    public function testMultipleInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('insert')
                ->once()
                ->with('insert into "USERS" ("EMAIL") select ? from dual union all select ? from dual ', ['foo', 'foo'])
                ->andReturn(true);
        $data[] = ['email' => 'foo'];
        $data[] = ['email' => 'foo'];
        $result = $builder->from('users')->insert($data);
        $this->assertTrue($result);
    }

    public function testInsertGetIdMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
                ->shouldReceive('processInsertGetId')
                ->once()
                ->with($builder, 'insert into "USERS" ("EMAIL") values (?) returning "ID" into ?', ['foo'], 'id')
                ->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertGetIdMethodRemovesExpressions()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
            ->shouldReceive('processInsertGetId')
            ->once()
            ->with($builder, 'insert into "USERS" ("EMAIL", "BAR") values (?, bar) returning "ID" into ?', ['foo'],
                'id')
            ->andReturn(1);
        $result = $builder->from('users')
            ->insertGetId(['email' => 'foo', 'bar' => new Raw('bar')],
                'id');
        $this->assertEquals(1, $result);
    }

    /**
     * @TODO: Fix sql for empty values.
     * @link https://github.com/yajra/laravel-oci8/issues/586
     */
    public function testInsertGetIdWithEmptyValues()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "USERS" () values () returning "ID" into ?', [], null);
        $builder->from('users')->insertGetId([]);
    }

    public function testInsertMethodRespectsRawBindings()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into "USERS" ("EMAIL") values (CURRENT TIMESTAMP)', [])
            ->andReturn(true);
        $result = $builder->from('users')->insert(['email' => new Raw('CURRENT TIMESTAMP')]);
        $this->assertTrue($result);
    }

    /**
     * @TODO: Fix raw expressions value.
     *      insert into "USERS" ("EMAIL") select UPPER('Foo') from dual union all select LOWER('Foo') from dual
     */
    public function testMultipleInsertsWithExpressionValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "USERS" ("EMAIL") select UPPER\'Foo\' from dual union all select UPPER\'Foo\' from dual ', [])->andReturn(true);
        $result = $builder->from('users')->insert([['email' => new Raw("UPPER('Foo')")], ['email' => new Raw("LOWER('Foo')")]]);
        $this->assertTrue($result);
    }

    public function testInsertLobMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
                ->shouldReceive('saveLob')
                ->once()
                ->with($builder,
                    'insert into "USERS" ("EMAIL", "MYBLOB") values (?, EMPTY_BLOB()) returning "MYBLOB", "ID" into ?, ?',
                    ['foo'], ['test data'])
                ->andReturn(1);
        $result = $builder->from('users')->insertLob(['email' => 'foo'], ['myblob' => 'test data'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertOnlyLobMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
                ->shouldReceive('saveLob')
                ->once()
                ->with($builder,
                    'insert into "USERS" ("MYBLOB") values (EMPTY_BLOB()) returning "MYBLOB", "ID" into ?, ?', [],
                    ['test data'])
                ->andReturn(1);
        $result = $builder->from('users')->insertLob([], ['myblob' => 'test data'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update "USERS" set "EMAIL" = ?, "NAME" = ? where "ID" = ?', ['foo', 'bar', 1])
            ->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    /**
     * @TODO: Add support for upsert.
     */
    protected function testUpsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "USERS" ("EMAIL", "NAME") values (?, ?), (?, ?) on conflict ("EMAIL") do update set "EMAIL" = "excluded"."EMAIL", "NAME" = "excluded"."NAME"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);
    }

    /**
     * @TODO: Add support for upsert.
     */
    protected function testUpsertMethodWithUpdateColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert into "USERS" ("EMAIL", "NAME") values (?, ?), (?, ?) on conflict ("EMAIL") do update set "NAME" = "excluded"."NAME"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);
    }

    public function testUpdateLobMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
                ->shouldReceive('saveLob')
                ->once()
                ->with($builder,
                    'update "USERS" set "EMAIL" = ?, "MYBLOB" = EMPTY_BLOB() where "ID" = ? returning "MYBLOB", "ID" into ?, ?',
                    ['foo', 1],
                    ['test data'])
                ->andReturn(1);
        $result = $builder->from('users')
                          ->where('id', '=', 1)
                          ->updateLob(['email' => 'foo'], ['myblob' => 'test data'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testUpdateOnlyLobMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
                ->shouldReceive('saveLob')
                ->once()
                ->with($builder,
                    'update "USERS" set "MYBLOB" = EMPTY_BLOB() where "ID" = ? returning "MYBLOB", "ID" into ?, ?',
                    [1],
                    ['test data'])
                ->andReturn(1);
        $result = $builder->from('users')
                          ->where('id', '=', 1)
                          ->updateLob([], ['myblob' => 'test data'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoins()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('update')
                ->once()
                ->with('update "USERS" inner join "ORDERS" on "USERS"."ID" = "ORDERS"."USER_ID" set "EMAIL" = ?, "NAME" = ? where "USERS"."ID" = ?',
                    ['foo', 'bar', 1])
                ->andReturn(1);
        $result = $builder->from('users')
                          ->join('orders', 'users.id', '=', 'orders.user_id')
                          ->where('users.id', '=', 1)
                          ->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodRespectsRaw()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('update')
                ->once()
                ->with('update "USERS" set "EMAIL" = foo, "NAME" = ? where "ID" = ?', ['bar', 1])
                ->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => new Raw('foo'), 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateOrInsertMethod()
    {
        $builder = m::mock(Builder::class.'[where,exists,insert]', [
            m::mock(ConnectionInterface::class),
            new OracleGrammar,
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(false);
        $builder->shouldReceive('insert')->once()->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

        $builder = m::mock(Builder::class.'[where,exists,update]', [
            m::mock(ConnectionInterface::class),
            new OracleGrammar,
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);
        $builder->shouldReceive('take')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['name' => 'bar'])->andReturn(1);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
    }

    public function testUpdateOrInsertMethodWorksWithEmptyUpdateValues()
    {
        $builder = m::spy(Builder::class.'[where,exists,update]', [
            m::mock(ConnectionInterface::class),
            new OracleGrammar,
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo']));
        $builder->shouldNotHaveReceived('update');
    }

    public function testDeleteMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('delete')
                ->once()
                ->with('delete from "USERS" where "EMAIL" = ?', ['foo'])
                ->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('delete')
                ->once()
                ->with('delete from "USERS" where "USERS"."ID" = ?', [1])
                ->andReturn(1);
        $result = $builder->from('users')->delete(1);
        $this->assertEquals(1, $result);
    }

    /**
     * @TODO: fix delete with join sql.
     */
    protected function testDeleteWithJoinMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "USERS" where "CTID" in (select "USERS"."CTID" from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" where "USERS"."EMAIL" = ?)', ['foo'])->andReturn(1);
        $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->where('users.email', '=', 'foo')->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "USERS" as "a" where "CTID" in (select "a"."CTID" from "USERS" as "a" inner join "USERS" as "b" on "a"."ID" = "b"."user_id" where "EMAIL" = ? order by "ID" asc limit 1)', ['foo'])->andReturn(1);
        $result = $builder->from('users AS a')->join('users AS b', 'a.id', '=', 'b.user_id')->where('email', '=', 'foo')->orderBy('id')->limit(1)->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "USERS" where "CTID" in (select "USERS"."CTID" from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID" where "USERS"."ID" = ? order by "ID" asc limit 1)', [1])->andReturn(1);
        $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->orderBy('id')->take(1)->delete(1);
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "USERS" where "CTID" in (select "USERS"."CTID" from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."user_id" and "USERS"."ID" = ? where "NAME" = ?)', [1, 'baz'])->andReturn(1);
        $result = $builder->from('users')
            ->join('contacts', function ($join) {
                $join->on('users.id', '=', 'contacts.user_id')
                    ->where('users.id', '=', 1);
            })->where('name', 'baz')
            ->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('delete')->once()->with('delete from "USERS" where "CTID" in (select "USERS"."CTID" from "USERS" inner join "CONTACTS" on "USERS"."ID" = "CONTACTS"."ID")', [])->andReturn(1);
        $result = $builder->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->delete();
        $this->assertEquals(1, $result);
    }

    public function testTruncateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('statement')->once()->with('truncate table "USERS"', []);
        $builder->from('users')->truncate();
    }

    public function testMergeWheresCanMergeWheresAndBindings()
    {
        $builder         = $this->getBuilder();
        $builder->wheres = ['foo'];
        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testProvidingNullWithOperatorsBuildsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertSame('select * from "USERS" where "FOO" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '=', null);
        $this->assertSame('select * from "USERS" where "FOO" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '!=', null);
        $this->assertSame('select * from "USERS" where "FOO" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '<>', null);
        $this->assertSame('select * from "USERS" where "FOO" is not null', $builder->toSql());
    }

    public function testProvidingNullOrFalseAsSecondParameterBuildsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from "USERS" where "FOO" is null', $builder->toSql());
    }

    public function testDynamicWhere()
    {
        $method     = 'whereFooBarAndBazOrQux';
        $parameters = ['corge', 'waldo', 'fred'];
        $grammar    = new OracleGrammar;
        $processor  = m::mock('\Yajra\Oci8\Query\Processors\OracleProcessor');
        $builder    = m::mock('Illuminate\Database\Query\Builder[where]',
            [m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor]);

        $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturn($builder);

        $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
    }

    public function testDynamicWhereIsNotGreedy()
    {
        $method     = 'whereIosVersionAndAndroidVersionOrOrientation';
        $parameters = ['6.1', '4.2', 'Vertical'];
        $builder    = m::mock(Builder::class)->makePartial();

        $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturnSelf();

        $builder->dynamicWhere($method, $parameters);
    }

    public function testCallTriggersDynamicWhere()
    {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    public function testBuilderThrowsExpectedExceptionWithUndefinedMethod()
    {
        $this->expectException(BadMethodCallException::class);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select');
        $builder->getProcessor()->shouldReceive('processSelect')->andReturn([]);

        $builder->noValidMethodHere();
    }

    public function testOracleLock()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertEquals('select * from "FOO" where "BAR" = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        try {
            $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        } catch (Oci8Exception $e) {
            // $this->assertEquals('select * from foo where bar = ? lock in share mode', $builder->toSql());
            $this->assertContains('Lock in share mode not yet supported!', $e->getMessage());
            $this->assertEquals(['baz'], $builder->getBindings());
        }
    }

    /**
     * @TODO: select with lock not yet supported.
     */
    protected function testSelectWithLockUsesWritePdo()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()
            ->with(m::any(), m::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock()->get();

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()
            ->with(m::any(), m::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false)->get();
    }

    public function testBindingOrder()
    {
        $expectedSql      = 'select * from "USERS" inner join "OTHERTABLE" on "BAR" = ? where "REGISTERED" = ? group by "CITY" having "POPULATION" > ? order by match ("FOO") against(?)';
        $expectedBindings = ['foo', 1, 3, 'bar'];

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('othertable', function ($join) {
            $join->where('bar', '=', 'foo');
        })->where('registered', 1)->groupBy('city')->having('population', '>', 3)->orderByRaw('match ("FOO") against(?)', ['bar']);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        // order of statements reversed
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByRaw('match ("FOO") against(?)', ['bar'])->having('population', '>', 3)->groupBy('city')->where('registered', 1)->join('othertable', function ($join) {
            $join->where('bar', '=', 'foo');
        });
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindings()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $builder->addBinding(['baz']);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindingsInCorrectOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuilders()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding(['baz']);
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuildersBindingOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding('foo', 'where');
        $builder->addBinding('baz', 'having');
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding('bar', 'where');
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testSubSelect()
    {
        $expectedSql      = 'select "FOO", "BAR", (select "BAZ" from "TWO" where "SUBKEY" = ?) as "SUB" from "ONE" where "KEY" = ?';
        $expectedBindings = ['subval', 'val'];

        $builder = $this->getBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $builder->selectSub(function ($query) {
            $query->from('two')->select('baz')->where('subkey', '=', 'subval');
        }, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $subBuilder = $this->getBuilder();
        $subBuilder->from('two')->select('baz')->where('subkey', '=', 'subval');
        $builder->selectSub($subBuilder, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->selectSub(['foo'], 'sub');
    }

    public function testSubSelectResetBindings()
    {
        $builder = $this->getBuilder();
        $builder->from('one')->selectSub(function ($query) {
            $query->from('two')->select('baz')->where('subkey', '=', 'subval');
        }, 'sub');

        $this->assertSame('select (select "BAZ" from "TWO" where "SUBKEY" = ?) as "SUB" from "ONE"', $builder->toSql());
        $this->assertEquals(['subval'], $builder->getBindings());

        $builder->select('*');

        $this->assertSame('select * from "ONE"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testUppercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function testLowercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function testCaseInsensitiveLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function testChunkWithLastChunkComplete()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3', 'foo4']);
        $chunk3 = collect([]);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(3, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkWithLastChunkPartial()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkCanBeStoppedByReturningFalse()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->never()->with(2, 2);
        $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);

            return false;
        });
    }

    public function testChunkWithCountZero()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = collect([]);
        $builder->shouldReceive('forPage')->once()->with(1, 0)->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunk(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkPaginatesUsingIdWithLastChunkComplete()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = collect([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
        $chunk3 = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithLastChunkPartial()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = collect([(object) ['someIdField' => 10]]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithCountZero()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(0, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunkById(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithAlias()
    {
        $builder           = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['table_id' => 1], (object) ['table_id' => 10]]);
        $chunk2 = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'table.id')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 10, 'table.id')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'table.id', 'table_id');
    }

    public function testPaginate()
    {
        $perPage  = 16;
        $columns  = ['test'];
        $pageName = 'page-name';
        $page     = 1;
        $builder  = $this->getMockQueryBuilder();
        $path     = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path'     => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithDefaultArguments()
    {
        $perPage  = 15;
        $pageName = 'page';
        $page     = 1;
        $builder  = $this->getMockQueryBuilder();
        $path     = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPageResolver(function () {
            return 1;
        });

        Paginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path'     => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWhenNoResults()
    {
        $perPage  = 15;
        $pageName = 'page';
        $page     = 1;
        $builder  = $this->getMockQueryBuilder();
        $path     = 'http://foo.bar?page=3';

        $results = [];

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(0);
        $builder->shouldNotReceive('forPage');
        $builder->shouldNotReceive('get');

        Paginator::currentPageResolver(function () {
            return 1;
        });

        Paginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, 0, $perPage, $page, [
            'path'     => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithSpecificColumns()
    {
        $perPage  = 16;
        $columns  = ['id', 'name'];
        $pageName = 'page-name';
        $page     = 1;
        $builder  = $this->getMockQueryBuilder();
        $path     = 'http://foo.bar?page=3';

        $results = collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path'     => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testWhereRowValues()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, 2]);
        $this->assertSame('select * from "ORDERS" where ("LAST_UPDATE", "ORDER_NUMBER") < (?, ?)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('company_id', 1)->orWhereRowValues(['last_update', 'order_number'], '<', [1, 2]);
        $this->assertSame('select * from "ORDERS" where "COMPANY_ID" = ? or ("LAST_UPDATE", "ORDER_NUMBER") < (?, ?)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, new Raw('2')]);
        $this->assertSame('select * from "ORDERS" where ("LAST_UPDATE", "ORDER_NUMBER") < (?, 2)', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereRowValuesArityMismatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of columns must match the number of values');

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update'], '<', [1, 2]);
    }

    public function testFromAs()
    {
        $builder = $this->getBuilder();
        $builder->from('sessions', 'as_session')->where('bar', '<', '10');
        $this->assertSame('select * from "SESSIONS" as_session where "BAR" < ?', $builder->toSql());
        $this->assertEquals(['10'], $builder->getBindings());
    }

    /**
     * @TODO: add json support?
     */
    public function testWhereJsonContains()
    {
        $this->expectExceptionMessage('This database engine does not support JSON contains operations.');

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
        $this->assertSame('select * from "USERS" where ("OPTIONS")::jsonb @> ?', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
        $this->assertSame('select * from "USERS" where ("USERS"."OPTIONS"->\'languages\')::jsonb @> ?', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'[\"en\"]'"));
        $this->assertSame('select * from "USERS" where "id" = ? or ("OPTIONS"->\'languages\')::jsonb @> \'["en"]\'', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testFromSub()
    {
        $builder = $this->getBuilder();
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
        }, 'sessions')->where('bar', '<', '10');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "USER_SESSIONS" where "FOO" = ?) "SESSIONS" where "BAR" < ?', $builder->toSql());
        $this->assertEquals(['1', '10'], $builder->getBindings());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->fromSub(['invalid'], 'sessions')->where('bar', '<', '10');
    }

    public function testFromSubWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
        }, 'sessions')->where('bar', '<', '10');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "PREFIX_USER_SESSIONS" where "FOO" = ?) "PREFIX_SESSIONS" where "BAR" < ?', $builder->toSql());
        $this->assertEquals(['1', '10'], $builder->getBindings());
    }

    public function testFromSubWithoutBindings()
    {
        $builder = $this->getBuilder();
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions');
        }, 'sessions');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "USER_SESSIONS") "SESSIONS"', $builder->toSql());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->fromSub(['invalid'], 'sessions');
    }

    public function testFromRaw()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"'));
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());
    }

    public function testFromRawWithWhereOnTheMainQuery()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at"'))->where('last_seen_at', '>', '1520652582');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at" where "LAST_SEEN_AT" > ?', $builder->toSql());
        $this->assertEquals(['1520652582'], $builder->getBindings());
    }

    public function testClone()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $clone = $builder->clone()->where('email', 'foo');

        $this->assertNotSame($builder, $clone);
        $this->assertSame('select * from "USERS"', $builder->toSql());
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $clone->toSql());
    }

    public function testCloneWithout()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['orders']);

        $this->assertSame('select * from "USERS" where "EMAIL" = ? order by "EMAIL" asc', $builder->toSql());
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $clone->toSql());
    }

    public function testCloneWithoutBindings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['wheres'])->cloneWithoutBindings(['where']);

        $this->assertSame('select * from "USERS" where "EMAIL" = ? order by "EMAIL" asc', $builder->toSql());
        $this->assertEquals([0 => 'foo'], $builder->getBindings());

        $this->assertSame('select * from "USERS" order by "EMAIL" asc', $clone->toSql());
        $this->assertEquals([], $clone->getBindings());
    }

    protected function getConnection()
    {
        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('getConfig')->andReturn('');
        $connection->shouldReceive('getDatabaseName')->andReturn('database');

        return $connection;
    }

    protected function getBuilder()
    {
        $grammar   = new OracleGrammar;
        $processor = m::mock(OracleProcessor::class);

        return new Builder($this->getConnection(), $grammar, $processor);
    }

    /**
     * @return m\MockInterface
     */
    protected function getMockQueryBuilder()
    {
        return m::mock(Builder::class, [
            m::mock(ConnectionInterface::class),
            new OracleGrammar,
            m::mock(OracleProcessor::class),
        ])->makePartial();
    }

    protected function getBuilderWithProcessor()
    {
        $grammar   = new OracleGrammar;
        $processor = new OracleProcessor;

        return new Builder(m::mock(ConnectionInterface::class), $grammar, $processor);
    }
}
