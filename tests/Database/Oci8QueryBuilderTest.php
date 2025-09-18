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
use Yajra\Oci8\Oci8Connection as Connection;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Yajra\Oci8\Query\Processors\OracleProcessor;

class Oci8QueryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_basic_select()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "USERS"', $builder->toSql());
    }

    public function test_basic_select_with_get_columns()
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

    public function test_basic_select_with_reserved_words()
    {
        $builder = $this->getBuilder();
        $builder->select('exists', 'drop', 'group')->from('users');
        $this->assertEquals('select "EXISTS", "DROP", "GROUP" from "USERS"', $builder->toSql());
    }

    public function test_basic_select_use_write_pdo()
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

    public function test_basic_table_wrapping_protects_quotation_marks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertSame('select * from "SOME""TABLE"', $builder->toSql());
    }

    public function test_alias_wrapping_as_whole_constant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertSame('select "X"."Y" as "FOO.BAR" from "BAZ"', $builder->toSql());
    }

    /**
     * @TODO: Correct output should also wrap x.
     *          select "W" "X"."Y"."Z" as "FOO.BAR" from "BAZ"
     */
    public function test_alias_wrapping_with_spaces_in_database_name()
    {
        $builder = $this->getBuilder();
        $builder->select('w x.y.z as foo.bar')->from('baz');
        $this->assertSame('select "W" x."Y"."Z" as "FOO.BAR" from "BAZ"', $builder->toSql());
    }

    public function test_adding_selects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select "FOO", "BAR", "BAZ", "BOOM" from "USERS"', $builder->toSql());
    }

    public function test_basic_select_with_prefix()
    {
        $builder = $this->getBuilder('prefix_');
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "PREFIX_USERS"', $builder->toSql());
    }

    public function test_basic_select_distinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select('foo', 'bar')->from('users');
        $this->assertEquals('select distinct "FOO", "BAR" from "USERS"', $builder->toSql());
    }

    public function test_basic_select_distinct_on_columns()
    {
        $builder = $this->getBuilder();
        $builder->distinct('foo')->select('foo', 'bar')->from('users');
        $this->assertSame('select distinct "FOO", "BAR" from "USERS"', $builder->toSql());
    }

    public function test_basic_alias()
    {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select "FOO" as "BAR" from "USERS"', $builder->toSql());
    }

    public function test_alias_with_prefix()
    {
        $builder = $this->getBuilder('prefix_');
        $builder->select('*')->from('users as people');
        $this->assertSame('select * from "PREFIX_USERS" "PREFIX_PEOPLE"', $builder->toSql());
    }

    public function test_join_aliases_with_prefix()
    {
        $builder = $this->getBuilder('prefix_');
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertSame('select * from "PREFIX_SERVICES" inner join "PREFIX_TRANSLATIONS" "PREFIX_T" on "PREFIX_T"."ITEM_ID" = "PREFIX_SERVICES"."ID"', $builder->toSql());
    }

    public function test_basic_table_wrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertSame('select * from "PUBLIC"."USERS"', $builder->toSql());
    }

    public function test_when_callback()
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

    public function test_when_callback_with_return()
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

    public function test_when_callback_with_default()
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

    public function test_unless_callback()
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

    public function test_unless_callback_with_return()
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

    public function test_unless_callback_with_default()
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

    public function test_tap_callback()
    {
        $callback = (fn ($query) => $query->where('id', '=', 1));

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->tap($callback)->where('email', 'foo');
        $this->assertSame('select * from "USERS" where "ID" = ? and "EMAIL" = ?', $builder->toSql());
    }

    public function test_basic_schema_wrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('acme.users');
        $this->assertEquals('select * from "ACME"."USERS"', $builder->toSql());
    }

    public function test_basic_schema_wrapping_reserved_words()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('schema.users');
        $this->assertEquals('select * from "SCHEMA"."USERS"', $builder->toSql());
    }

    public function test_basic_column_wrapping_reserved_words()
    {
        $builder = $this->getBuilder();
        $builder->select('order')->from('users');
        $this->assertEquals('select "ORDER" from "USERS"', $builder->toSql());
    }

    public function test_basic_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_basic_wheres_with_reserved_words()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('blob', '=', 1);
        $this->assertEquals('select * from "USERS" where "BLOB" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_wheres_with_array_value()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '!=', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" != ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '<>', [12, 30]);
        $this->assertSame('select * from "USERS" where "ID" <> ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());
    }

    public function test_date_based_wheres_accepts_two_arguments()
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

    public function test_date_based_or_wheres_accepts_two_arguments()
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

    public function test_date_based_wheres_expression_is_not_bound()
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

    public function test_where_date()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', new Raw('NOW()'));
        $this->assertSame('select * from "USERS" where trunc("CREATED_AT") = NOW()', $builder->toSql());
    }

    public function test_where_day()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 20);
        $this->assertEquals('select * from "USERS" where extract (day from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 20], $builder->getBindings());
    }

    public function test_or_where_day()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertSame('select * from "USERS" where extract (day from "CREATED_AT") = ? or extract (day from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_where_month()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 12);
        $this->assertEquals('select * from "USERS" where extract (month from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());
    }

    public function test_or_where_month()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertSame('select * from "USERS" where extract (month from "CREATED_AT") = ? or extract (month from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function test_where_year()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2015);
        $this->assertEquals('select * from "USERS" where extract (year from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 2015], $builder->getBindings());
    }

    public function test_or_where_year()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertSame('select * from "USERS" where extract (year from "CREATED_AT") = ? or extract (year from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function test_where_time()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from "USERS" where extract (time from "CREATED_AT") >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function test_where_time_operator_optional()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from "USERS" where extract (time from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function test_where_like()
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

    public function test_where_betweens()
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

    public function test_where_between_columns()
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

    public function test_basic_or_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "EMAIL" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_raw_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
        $this->assertEquals('select * from "USERS" where id = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_raw_or_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
        $this->assertEquals('select * from "USERS" where "ID" = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_basic_where_ins()
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

    public function test_basic_where_not_ins()
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

    public function test_raw_where_ins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [new Raw(1)]);
        $this->assertSame('select * from "USERS" where "ID" in (1)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new Raw(1)]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" in (1)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_empty_where_ins()
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

    public function test_empty_where_not_ins()
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

    public function test_where_integer_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_or_where_integer_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_where_integer_not_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" not in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_or_where_integer_not_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "USERS" where "ID" = ? or "ID" not in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_empty_where_integer_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', []);
        $this->assertSame('select * from "USERS" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_empty_where_integer_not_in_raw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', []);
        $this->assertSame('select * from "USERS" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_basic_where_column()
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

    public function test_array_where_column()
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

    public function test_where_full_text_with_single_parameter()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereFullText('name', 'johnny');
        $this->assertSame('select * from "USERS" where CONTAINS("NAME", ?, 1) > 0', $builder->toSql());
        $this->assertEquals(['johnny'], $builder->getBindings());
    }

    public function test_where_full_text_with_multiple_parameters()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereFullText(['firstname', 'lastname'], 'johnny');
        $this->assertSame('select * from "USERS" where CONTAINS("FIRSTNAME", ?, 1) > 0 and CONTAINS("LASTNAME", ?, 2) > 0',
            $builder->toSql());
        $this->assertEquals(['johnny'], $builder->getBindings());
    }

    public function test_where_full_text_with_logical_or_operator()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereFullText(['firstname', 'lastname'], 'johnny', [], 'or');
        $this->assertSame('select * from "USERS" where CONTAINS("FIRSTNAME", ?, 1) > 0 or CONTAINS("LASTNAME", ?, 2) > 0',
            $builder->toSql());
        $this->assertEquals(['johnny'], $builder->getBindings());
    }

    public function test_or_where_full_text_with_single_parameter()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orWhereFullText('firstname', 'johnny');
        $this->assertSame('select * from "USERS" where CONTAINS("FIRSTNAME", ?, 1) > 0', $builder->toSql());
        $this->assertEquals(['johnny'], $builder->getBindings());
    }

    public function test_or_where_full_text_with_multiple_parameters()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orWhereFullText('firstname', 'johnny')->orWhereFullText('lastname', 'white');
        $this->assertSame('select * from "USERS" where CONTAINS("FIRSTNAME", ?, 1) > 0 or CONTAINS("LASTNAME", ?, 2) > 0',
            $builder->toSql());
        $this->assertEquals(['johnny', 'white'], $builder->getBindings());
    }

    public function test_unions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertSame('(select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_union_alls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_multiple_unions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?) union (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_multiple_union_alls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('(select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?) union all (select * from "USERS" where "ID" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_union_order_bys()
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
    public function test_union_limits_and_offsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        // $this->assertSame('(select * from "USERS") union (select * from "DOGS") limit 10 offset 5', $builder->toSql());
        $this->assertSame('(select * from "USERS") union (select * from "DOGS")', $builder->toSql());

        $builder = $this->getBuilder();
        // $expectedSql = '(select "A" from "T1" where "A" = ? and "B" = ?) union (select "A" from "T2" where "A" = ? and "B" = ?) order by "A" asc limit 10';
        $expectedSql = '(select "A" from "T1" where "A" = ? and "B" = ?) union (select "A" from "T2" where "A" = ? and "B" = ?) order by "A" asc';
        $union = $this->getBuilder()->select('a')->from('t2')->where('a', 11)->where('b', 2);
        $builder->select('a')->from('t1')->where('a', 10)->where('b', 1)->union($union)->orderBy('a')->limit(10);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([0 => 10, 1 => 1, 2 => 11, 3 => 2], $builder->getBindings());
    }

    public function test_union_with_join()
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

    public function test_union_aggregate()
    {
        $expected = 'select count(*) as aggregate from ((select * from "POSTS") union (select * from "VIDEOS")) "TEMP_TABLE"';
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
        $builder->getProcessor()->shouldReceive('processSelect')->once();
        $builder->from('posts')->union($this->getBuilder()->from('videos'))->count();
    }

    public function test_basic_where_in_thousands()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', range(1, 1001));
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" in (%s) or "ID" in (?))',
            mb_substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(range(1, 1001), $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', range(1, 1001))->where('id', 1);
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" in (%s) or "ID" in (?)) and "ID" = ?',
            mb_substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
    }

    public function test_basic_where_not_in_thousands()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', range(1, 1001));
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" not in (%s) and "ID" not in (?))',
            mb_substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(range(1, 1001), $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', range(1, 1001))->where('id', 1);
        $bindings = str_repeat('?, ', 1000);
        $expected = sprintf(
            'select * from "USERS" where ("ID" not in (%s) and "ID" not in (?)) and "ID" = ?',
            mb_substr($bindings, 0, 2998)
        );
        $this->assertEquals($expected, $builder->toSql());
    }

    public function test_sub_select_where_ins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals(
            'select * from "USERS" where "ID" in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 ) t2 where t2."rn" between 1 and 3)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals(
            'select * from "USERS" where "ID" not in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 ) t2 where t2."rn" between 1 and 3)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());
    }

    public function test_basic_where_nulls()
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

    public function test_array_where_nulls()
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

    public function test_basic_where_not_nulls()
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

    public function test_array_where_not_nulls()
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

    public function test_group_bys()
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

    public function test_order_bys()
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

    public function test_reorder()
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

    public function test_order_by_sub_queries()
    {
        $expected = 'select * from "USERS" order by (select * from (select "CREATED_AT" from "LOGINS" where "USER_ID" = "USERS"."ID") where rownum = 1)';
        $subQuery = (fn ($query) => $query->select('created_at')->from('logins')->whereColumn('user_id', 'users.id')->limit(1));

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

    public function test_order_by_invalid_direction_param()
    {
        $this->expectException(InvalidArgumentException::class);

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('age', 'asec');
    }

    public function test_havings()
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

    public function test_having_shortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', 1)->orHaving('email', 2);
        $this->assertSame('select * from "USERS" having "EMAIL" = ? or "EMAIL" = ?', $builder->toSql());
    }

    public function test_having_followed_by_select_get()
    {
        $builder = $this->getBuilder();
        $query = 'select "CATEGORY", count(*) as "TOTAL" from "ITEM" where "DEPARTMENT" = ? group by "CATEGORY" having "TOTAL" > ?';
        $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular', 3], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "TOTAL"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3)->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());

        // Using \Raw value
        $builder = $this->getBuilder();
        $query = 'select "CATEGORY", count(*) as "TOTAL" from "ITEM" where "DEPARTMENT" = ? group by "CATEGORY" having "TOTAL" > 3';
        $builder->getConnection()->shouldReceive('select')->once()->with($query, ['popular'], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "TOTAL"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new Raw('3'))->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());
    }

    public function test_raw_havings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "USERS" having user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "USERS" having "BAZ" = ? or user_foo < user_bar', $builder->toSql());
    }

    public function test_offset()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" >= 11',
            $builder->toSql()
        );
    }

    public function test_limits_and_offsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 6 and 15',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(5)->take(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 6 and 15',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(-5)->take(10);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 10',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 16 and 30',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 15',
            $builder->toSql()
        );
    }

    public function test_limit_and_offset_to_paginate_one()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(0)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 1',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(1)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 2 and 2',
            $builder->toSql()
        );
    }

    public function test_for_page()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 16 and 30',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 15);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 15',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 15',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 0);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 0',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 0);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 0',
            $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 0);
        $this->assertSame(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 0',
            $builder->toSql());
    }

    public function test_get_count_for_pagination_with_bindings()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->selectSub(function ($q) {
            $q->select('body')->from('posts')->where('id', 4);
        }, 'post');

        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
        $this->assertEquals([4], $builder->getBindings());
    }

    public function test_get_count_for_pagination_with_column_aliases()
    {
        $builder = $this->getBuilder();
        $columns = ['body as post_body', 'teaser', 'posts.created as published'];
        $builder->from('posts')->select($columns);

        $builder->getConnection()->shouldReceive('select')->once()->with('select count("BODY", "TEASER", "POSTS"."CREATED") as aggregate from "POSTS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);

        $count = $builder->getCountForPagination($columns);
        $this->assertEquals(1, $count);
    }

    public function test_get_count_for_pagination_with_union()
    {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'));

        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from ((select "ID" from "POSTS") union (select "ID" from "VIDEOS")) "TEMP_TABLE"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function test_where_shortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "NAME" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_where_with_array_conditions()
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

    public function test_nested_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        $this->assertEquals('select * from "USERS" where "EMAIL" = ? or ("NAME" = ? and "AGE" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }

    public function test_nested_where_bindings()
    {
        $builder = $this->getBuilder();
        $builder->where('email', '=', 'foo')->where(function ($q) {
            $q->selectRaw('?', ['ignore'])->where('name', '=', 'bar');
        });
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function test_full_sub_selects()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function ($q) {
            $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
        });

        $this->assertEquals('select * from "USERS" where "EMAIL" = ? or "ID" = (select max(id) from "USERS" where "EMAIL" = ?)',
            $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function test_where_exists()
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

    public function test_basic_joins()
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

    public function test_cross_join_subs()
    {
        $builder = $this->getBuilder();
        $builder->selectRaw('(sale / overall.sales) * 100 AS percent_of_total')->from('sales')->crossJoinSub($this->getBuilder()->selectRaw('SUM(sale) AS sales')->from('sales'), 'overall');
        $this->assertSame('select (sale / overall.sales) * 100 AS percent_of_total from "SALES" cross join (select SUM(sale) AS sales from "SALES") "OVERALL"', $builder->toSql());
    }

    public function test_complex_join()
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

    public function test_join_where_null()
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

    public function test_join_where_not_null()
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

    public function test_join_where_in()
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

    public function test_join_where_in_subquery()
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

    public function test_join_where_not_in()
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

    public function test_joins_with_nested_conditions()
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

    public function test_joins_with_advanced_conditions()
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

    public function test_joins_with_subquery_condition()
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

    public function test_joins_with_advanced_subquery_condition()
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

    public function test_joins_with_nested_joins()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
        });
        $this->assertSame('select "USERS"."ID", "CONTACTS"."ID", "CONTACT_TYPES"."ID" from "USERS" left join ("CONTACTS" inner join "CONTACT_TYPES" on "CONTACTS"."CONTACT_TYPE_ID" = "CONTACT_TYPES"."ID") on "USERS"."ID" = "CONTACTS"."ID"', $builder->toSql());
    }

    public function test_joins_with_multiple_nested_joins()
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

    public function test_joins_with_nested_join_with_advanced_subquery_condition()
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

    public function test_join_sub()
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

        $builder = $this->getBuilder();
        $eloquentBuilder = new EloquentBuilder($this->getBuilder()->from('contacts'));
        $eloquentBuilder->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->joinSub($eloquentBuilder, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" inner join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $builder = $this->getBuilder();
        $sub1 = $this->getBuilder()->from('contacts')->where('name', 'foo');
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

    public function test_join_sub_with_prefix()
    {
        $builder = $this->getBuilder('prefix_');
        $builder->from('users')->joinSub('select * from "CONTACTS"', 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "PREFIX_USERS" inner join (select * from "CONTACTS") "PREFIX_SUB" on "PREFIX_USERS"."ID" = "PREFIX_SUB"."ID"', $builder->toSql());
    }

    public function test_left_join_sub()
    {
        $builder = $this->getBuilder();
        $sub = $this->getBuilder()->from('contacts');
        $sub->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->leftJoinSub($sub, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" left join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('users')->leftJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
    }

    public function test_right_join_sub()
    {
        $builder = $this->getBuilder();
        $sub = $this->getBuilder()->from('contacts');
        $sub->getConnection()->shouldReceive('getDatabaseName')->andReturn('oracle');
        $builder->from('users')->rightJoinSub($sub, 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "USERS" right join (select * from "CONTACTS") "SUB" on "USERS"."ID" = "SUB"."ID"',
            $builder->toSql());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('users')->rightJoinSub(['foo'], 'sub', 'users.id', '=', 'sub.id');
    }

    public function test_raw_expressions_in_select()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');
        $this->assertEquals('select substr(foo, 6) from "USERS"', $builder->toSql());
    }

    public function test_find_returns_first_result_by_id()
    {
        $builder = $this->getBuilder();
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
            ->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->find(1);
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function test_first_method_returns_first_result()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')
            ->once()
            ->with('select * from (select * from "USERS" where "ID" = ?) where rownum = 1',
                [1], true)
            ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar']])
            ->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->first();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function test_pluck_method_gets_collection_of_column_values()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function test_implode()
    {
        // Test without glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
        $this->assertSame('barbaz', $results);

        // Test with glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
        $this->assertSame('bar,baz', $results);
    }

    public function test_value_method_returns_single_column()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select * from (select "FOO" from "USERS" where "ID" = ?) where rownum = 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [['foo' => 'bar']])->andReturn([['foo' => 'bar']]);
        $results = $builder->from('users')->where('id', '=', 1)->value('foo');
        $this->assertSame('bar', $results);
    }

    public function test_list_methods_gets_array_of_column_values()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
            ->andReturnUsing(fn ($query, $results) => $results);
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
            ->andReturnUsing(fn ($query, $results) => $results);
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function test_aggregate_functions()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
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
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select min("ID") as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with('select sum("ID") as aggregate from "USERS"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function test_exists_or()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->doesntExistOr(fn () => 123);
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            throw new RuntimeException;
        });
        $this->assertTrue($results);
    }

    public function test_doesnt_exists_or()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->existsOr(fn () => 123);
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->existsOr(function () {
            throw new RuntimeException;
        });
        $this->assertTrue($results);
    }

    public function test_aggregate_reset_followed_by_get()
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
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('users')->select('column1', 'column2');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $sum = $builder->sum('id');
        $this->assertEquals(2, $sum);
        $result = $builder->get();
        $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result->all());
    }

    public function test_aggregate_reset_followed_by_select_get()
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
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->select('column2', 'column3')->get();
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function test_aggregate_reset_followed_by_get_with_columns()
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
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->get(['column2', 'column3']);
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function test_aggregate_with_sub_select()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $builder->from('users')->selectSub(function ($query) {
            $query->from('posts')->select('foo')->where('title', 'foo');
        }, 'post');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function test_sub_queries_bindings()
    {
        $builder = $this->getBuilder();
        $second = $this->getBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
        $third = $this->getBuilder()
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

    public function test_aggregate_count_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);
    }

    public function test_aggregate_exists_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')
            ->once()
            ->with('select 1 as "exists" from "USERS" where rownum = 1', [], true)
            ->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);
    }

    public function test_aggregate_max_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select max("ID") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);
    }

    public function test_aggregate_min_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select min("ID") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);
    }

    public function test_aggregate_sum_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select sum("ID") as aggregate from "USERS"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(fn ($builder, $results) => $results);
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function test_insert_method()
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

    public function test_insert_using_method()
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

    public function test_insert_using_invalid_subquery()
    {
        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('table1')->insertUsing(['foo'], ['bar']);
    }

    public function test_insert_or_ignore_method()
    {
        $expected = 'merge into "USERS" using (select ? as "EMAIL" from dual) "LARAVEL_SOURCE" on ("LARAVEL_SOURCE"."EMAIL" = "USERS"."EMAIL") when not matched then insert ("EMAIL") values ("LARAVEL_SOURCE"."EMAIL")';
        $builder = $this->getBuilder();
        $grammar = $builder->getGrammar();
        $builder->getConnection()
            ->shouldReceive('affectingStatement')
            ->once()
            ->with($expected, ['foo'])
            ->andReturn(1);

        $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
        $this->assertEquals(1, $result);
        $this->assertSame($expected, $grammar->compileInsertOrIgnore($builder, [['email' => 'foo']]));
    }

    public function test_insert_or_ignore_method_on_cache_store()
    {
        $expected = 'merge into "CACHE" using (select ? as "KEY", ? as "VALUES", ? as "EXPIRATION" from dual) "LARAVEL_SOURCE" on ("LARAVEL_SOURCE"."KEY" = "CACHE"."KEY") when not matched then insert ("KEY", "VALUES", "EXPIRATION") values ("LARAVEL_SOURCE"."KEY", "LARAVEL_SOURCE"."VALUES", "LARAVEL_SOURCE"."EXPIRATION")';
        $builder = $this->getBuilder();
        $grammar = $builder->getGrammar();
        $builder->getConnection()
            ->shouldReceive('affectingStatement')
            ->once()
            ->with($expected, ['foo', 'bar', 1234567890])
            ->andReturn(1);

        $values = ['key' => 'foo', 'values' => 'bar', 'expiration' => 1234567890];
        $result = $builder->from('cache')->insertOrIgnore($values);
        $this->assertEquals(1, $result);
        $this->assertSame($expected, $grammar->compileInsertOrIgnore($builder, [$values]));
    }

    public function test_multiple_insert_method()
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

    public function test_insert_get_id_method()
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

    public function test_insert_get_id_method_removes_expressions()
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
     *
     * @link https://github.com/yajra/laravel-oci8/issues/586
     */
    public function test_insert_get_id_with_empty_values()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into "USERS" () values () returning "ID" into ?', [], null);
        $builder->from('users')->insertGetId([]);
    }

    public function test_insert_method_respects_raw_bindings()
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
    public function test_multiple_inserts_with_expression_values()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('insert')->once()->with('insert into "USERS" ("EMAIL") select UPPER\'Foo\' from dual union all select UPPER\'Foo\' from dual ', [])->andReturn(true);
        $result = $builder->from('users')->insert([['email' => new Raw("UPPER('Foo')")], ['email' => new Raw("LOWER('Foo')")]]);
        $this->assertTrue($result);
    }

    public function test_insert_lob_method()
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

    public function test_insert_only_lob_method()
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

    public function test_update_method()
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

    public function test_upsert_method()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('affectingStatement')
            ->once()
            ->with('merge into "USERS" using (select ? as "EMAIL", ? as "NAME" from dual union all select ? as "EMAIL", ? as "NAME" from dual) "LARAVEL_SOURCE" on ("LARAVEL_SOURCE"."EMAIL" = "USERS"."EMAIL") when matched then update set "NAME" = "LARAVEL_SOURCE"."NAME" when not matched then insert ("EMAIL", "NAME") values ("LARAVEL_SOURCE"."EMAIL", "LARAVEL_SOURCE"."NAME")', ['foo', 'bar', 'foo2', 'bar2'])
            ->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);
    }

    public function test_upsert_method_with_update_columns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('affectingStatement')
            ->once()
            ->with('merge into "USERS" using (select ? as "EMAIL", ? as "NAME" from dual union all select ? as "EMAIL", ? as "NAME" from dual) "LARAVEL_SOURCE" on ("LARAVEL_SOURCE"."EMAIL" = "USERS"."EMAIL") when matched then update set "NAME" = "LARAVEL_SOURCE"."NAME" when not matched then insert ("EMAIL", "NAME") values ("LARAVEL_SOURCE"."EMAIL", "LARAVEL_SOURCE"."NAME")', ['foo', 'bar', 'foo2', 'bar2'])
            ->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);
    }

    public function test_update_lob_method()
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

    public function test_update_only_lob_method()
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

    public function test_update_method_with_joins()
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

    public function test_update_method_respects_raw()
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

    public function test_update_or_insert_method()
    {
        $builder = m::mock(Builder::class.'[where,exists,insert]', [
            m::mock(ConnectionInterface::class),
            $this->getGrammar(),
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(false);
        $builder->shouldReceive('insert')->once()->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

        $builder = m::mock(Builder::class.'[where,exists,update]', [
            m::mock(ConnectionInterface::class),
            $this->getGrammar(),
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);
        $builder->shouldReceive('take')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['name' => 'bar'])->andReturn(1);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
    }

    public function test_update_or_insert_method_works_with_empty_update_values()
    {
        $builder = m::spy(Builder::class.'[where,exists,update]', [
            m::mock(ConnectionInterface::class),
            $this->getGrammar(),
            m::mock(OracleProcessor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(m::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo']));
        $builder->shouldNotHaveReceived('update');
    }

    public function test_delete_method()
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

    public function getGrammar(string $prefix = ''): OracleGrammar
    {
        return new OracleGrammar($this->getConnection(prefix: $prefix));
    }

    /**
     * @TODO: fix delete with join sql.
     */
    protected function test_delete_with_join_method()
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

    public function test_truncate_method()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('statement')->once()->with('truncate table "USERS"', []);
        $builder->from('users')->truncate();
    }

    public function test_merge_wheres_can_merge_wheres_and_bindings()
    {
        $builder = $this->getBuilder();
        $builder->wheres = ['foo'];
        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function test_providing_null_with_operators_builds_correctly()
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

    public function test_providing_null_or_false_as_second_parameter_builds_correctly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from "USERS" where "FOO" is null', $builder->toSql());
    }

    public function test_dynamic_where()
    {
        $method = 'whereFooBarAndBazOrQux';
        $parameters = ['corge', 'waldo', 'fred'];
        $grammar = $this->getGrammar();
        $processor = m::mock(\Yajra\Oci8\Query\Processors\OracleProcessor::class);
        $builder = m::mock('Illuminate\Database\Query\Builder[where]',
            [m::mock(\Illuminate\Database\ConnectionInterface::class), $grammar, $processor]);

        $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturn($builder);

        $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
    }

    public function test_dynamic_where_is_not_greedy()
    {
        $method = 'whereIosVersionAndAndroidVersionOrOrientation';
        $parameters = ['6.1', '4.2', 'Vertical'];
        $builder = m::mock(Builder::class)->makePartial();

        $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturnSelf();

        $builder->dynamicWhere($method, $parameters);
    }

    public function test_call_triggers_dynamic_where()
    {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    public function test_builder_throws_expected_exception_with_undefined_method()
    {
        $this->expectException(BadMethodCallException::class);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select');
        $builder->getProcessor()->shouldReceive('processSelect')->andReturn([]);

        $builder->noValidMethodHere();
    }

    public function test_oracle_lock()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('beginTransaction')
            ->shouldReceive('select')
            ->shouldReceive('commit');

        $builder->getProcessor()
            ->shouldReceive('processSelect');

        $builder->select('*')
            ->from('foo')
            ->where('bar', '=', 'baz')
            ->lockForUpdate()
            ->first();
        $this->assertEquals('select * from (select * from "FOO" where "BAR" = ?) where rownum = 1 for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertEquals('select * from "FOO" where "BAR" = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertEquals('select * from "FOO" where "BAR" = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function test_oracle_lock_with_order()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('beginTransaction')
            ->shouldReceive('select')
            ->shouldReceive('commit');

        $builder->getProcessor()
            ->shouldReceive('processSelect');

        $builder->select('*')
            ->from('foo')
            ->where('bar', '=', 'baz')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $this->assertEquals(
            'select * from (select * from "FOO" where "BAR" = ?) where rownum = 1 for update order by "ID" asc',
            $builder->toSql()
        );
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function test_binding_order()
    {
        $expectedSql = 'select * from "USERS" inner join "OTHERTABLE" on "BAR" = ? where "REGISTERED" = ? group by "CITY" having "POPULATION" > ? order by match ("FOO") against(?)';
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

    public function test_add_binding_with_array_merges_bindings()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $builder->addBinding(['baz']);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function test_add_binding_with_array_merges_bindings_in_correct_order()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function test_merge_builders()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding(['baz']);
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function test_merge_builders_binding_order()
    {
        $builder = $this->getBuilder();
        $builder->addBinding('foo', 'where');
        $builder->addBinding('baz', 'having');
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding('bar', 'where');
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function test_sub_select()
    {
        $expectedSql = 'select "FOO", "BAR", (select "BAZ" from "TWO" where "SUBKEY" = ?) as "SUB" from "ONE" where "KEY" = ?';
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

    public function test_sub_select_reset_bindings()
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

    public function test_uppercase_leading_booleans_are_removed()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function test_lowercase_leading_booleans_are_removed()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function test_case_insensitive_leading_booleans_are_removed()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertSame('select * from "USERS" where "NAME" = ?', $builder->toSql());
    }

    public function test_chunk_with_last_chunk_complete()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3', 'foo4']);
        $chunk3 = collect([]);

        $builder->shouldReceive('getOffset')->once()->andReturnNull();
        $builder->shouldReceive('getLimit')->once()->andReturnNull();
        $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
        $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
        $builder->shouldReceive('offset')->once()->with(4)->andReturnSelf();
        $builder->shouldReceive('limit')->times(3)->with(2)->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function test_chunk_with_last_chunk_partial()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);

        $builder->shouldReceive('getOffset')->once()->andReturnNull();
        $builder->shouldReceive('getLimit')->once()->andReturnNull();
        $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
        $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
        $builder->shouldReceive('limit')->twice()->with(2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function test_chunk_can_be_stopped_by_returning_false()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);
        $builder->shouldReceive('getOffset')->once()->andReturnNull();
        $builder->shouldReceive('getLimit')->once()->andReturnNull();
        $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
        $builder->shouldReceive('limit')->once()->with(2)->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);

            return false;
        });
    }

    public function test_chunk_with_count_zero()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $builder->shouldReceive('getOffset')->once()->andReturnNull();
        $builder->shouldReceive('getLimit')->once()->andReturnNull();
        $builder->shouldReceive('offset')->never();
        $builder->shouldReceive('limit')->never();
        $builder->shouldReceive('get')->never();

        $builder->chunk(0, function () {
            $this->fail('Should never be called.');
        });
    }

    public function test_chunk_by_id_on_arrays()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([['someIdField' => 1], ['someIdField' => 2]]);
        $chunk2 = collect([['someIdField' => 10], ['someIdField' => 11]]);
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

    public function test_chunk_paginates_using_id_with_last_chunk_complete()
    {
        $builder = $this->getMockQueryBuilder();
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

    public function test_chunk_paginates_using_id_with_last_chunk_partial()
    {
        $builder = $this->getMockQueryBuilder();
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

    public function test_chunk_paginates_using_id_with_count_zero()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $builder->shouldReceive('forPageAfterId')->never();
        $builder->shouldReceive('get')->never();

        $builder->chunkById(0, function () {
            $this->fail('Should never be called.');
        }, 'someIdField');
    }

    public function test_chunk_paginates_using_id_with_alias()
    {
        $builder = $this->getMockQueryBuilder();
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

    public function test_chunk_paginates_using_id_desc()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'desc'];

        $chunk1 = collect([(object) ['someIdField' => 10], (object) ['someIdField' => 1]]);
        $chunk2 = collect([]);
        $builder->shouldReceive('forPageBeforeId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageBeforeId')->once()->with(2, 1, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkByIdDesc(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function test_paginate()
    {
        $perPage = 16;
        $columns = ['test'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPathResolver(fn () => $path);

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function test_paginate_with_default_arguments()
    {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPageResolver(fn () => 1);

        Paginator::currentPathResolver(fn () => $path);

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function test_paginate_when_no_results()
    {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = [];

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(0);
        $builder->shouldNotReceive('forPage');
        $builder->shouldNotReceive('get');

        Paginator::currentPageResolver(fn () => 1);

        Paginator::currentPathResolver(fn () => $path);

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, 0, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function test_paginate_with_specific_columns()
    {
        $perPage = 16;
        $columns = ['id', 'name'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

        $builder->shouldReceive('getCountForPagination')->once()->andReturn(2);
        $builder->shouldReceive('forPage')->once()->with($page, $perPage)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn($results);

        Paginator::currentPathResolver(fn () => $path);

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function test_where_row_values()
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

    public function test_where_row_values_arity_mismatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of columns must match the number of values');

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update'], '<', [1, 2]);
    }

    public function test_from_as()
    {
        $builder = $this->getBuilder();
        $builder->from('sessions', 'as_session')->where('bar', '<', '10');
        $this->assertSame('select * from "SESSIONS" as_session where "BAR" < ?', $builder->toSql());
        $this->assertEquals(['10'], $builder->getBindings());
    }

    /**
     * @TODO: add json support?
     */
    public function test_where_json_contains()
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

    public function test_from_sub()
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

    public function test_from_sub_with_prefix()
    {
        $builder = $this->getBuilder('prefix_');
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
        }, 'sessions')->where('bar', '<', '10');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "PREFIX_USER_SESSIONS" where "FOO" = ?) "PREFIX_SESSIONS" where "BAR" < ?', $builder->toSql());
        $this->assertEquals(['1', '10'], $builder->getBindings());
    }

    public function test_from_sub_without_bindings()
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

    public function test_from_raw()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"'));
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());
    }

    public function test_from_raw_with_where_on_the_main_query()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at"'))->where('last_seen_at', '>', '1520652582');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at" where "LAST_SEEN_AT" > ?', $builder->toSql());
        $this->assertEquals(['1520652582'], $builder->getBindings());
    }

    public function test_clone()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $clone = $builder->clone()->where('email', 'foo');

        $this->assertNotSame($builder, $clone);
        $this->assertSame('select * from "USERS"', $builder->toSql());
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $clone->toSql());
    }

    public function test_clone_without()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['orders']);

        $this->assertSame('select * from "USERS" where "EMAIL" = ? order by "EMAIL" asc', $builder->toSql());
        $this->assertSame('select * from "USERS" where "EMAIL" = ?', $clone->toSql());
    }

    public function test_clone_without_bindings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['wheres'])->cloneWithoutBindings(['where']);

        $this->assertSame('select * from "USERS" where "EMAIL" = ? order by "EMAIL" asc', $builder->toSql());
        $this->assertEquals([0 => 'foo'], $builder->getBindings());

        $this->assertSame('select * from "USERS" order by "EMAIL" asc', $clone->toSql());
        $this->assertEquals([], $clone->getBindings());
    }

    public function test_random_order()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->inRandomOrder();

        $this->assertSame('select * from "USERS" where "EMAIL" = ? order by DBMS_RANDOM.RANDOM', $builder->toSql());
        $this->assertEquals([0 => 'foo'], $builder->getBindings());
    }

    public function test_where_like_clause()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereLike('id', '1', true);
        $this->assertSame('select * from "USERS" where "ID" like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereLike('id', '1', false);
        $this->assertSame('select * from "USERS" where upper("ID") like upper(?)', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereLike('id', '1', true);
        $this->assertSame('select * from "USERS" where "ID" like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotLike('id', '1');
        $this->assertSame('select * from "USERS" where upper("ID") not like upper(?)', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotLike('id', '1', false);
        $this->assertSame('select * from "USERS" where upper("ID") not like upper(?)', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotLike('id', '1', true);
        $this->assertSame('select * from "USERS" where "ID" not like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());
    }

    protected function getConnection(string $prefix = '', string $schemaPrefix = '')
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn('database');
        $connection->shouldReceive('getTablePrefix')->andReturn($prefix);
        $connection->shouldReceive('getSchemaPrefix')->andReturn($schemaPrefix);
        $connection->shouldReceive('setSchemaPrefix');
        $connection->shouldReceive('getMaxLength')->andReturn(30);
        $connection->shouldReceive('setMaxLength');
        $connection->shouldReceive('getConfig')->andReturn([]);

        return $connection;
    }

    protected function getBuilder(string $prefix = '')
    {
        $connection = $this->getConnection(prefix: $prefix);
        $grammar = new OracleGrammar($connection);
        $processor = m::mock(OracleProcessor::class);

        return new Builder($connection, $grammar, $processor);
    }

    /**
     * @return \Mockery\MockInterface|\Illuminate\Database\Query\Builder
     */
    protected function getMockQueryBuilder()
    {
        return m::mock(Builder::class, [
            $connection = $this->getConnection(),
            new OracleGrammar($connection),
            m::mock(OracleProcessor::class),
        ])->makePartial();
    }

    protected function getBuilderWithProcessor($prefix = '')
    {
        $grammar = $this->getGrammar($prefix);
        $processor = new OracleProcessor;

        return new Builder(m::mock(ConnectionInterface::class), $grammar, $processor);
    }

    public function test_order_by_not_duplicated_with_lock()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name')->lock();

        $sql = $builder->toSql();

        // Should contain ORDER BY only once
        $this->assertEquals(1, substr_count(strtolower((string) $sql), 'order by'));
        $this->assertStringContainsString('order by', strtolower((string) $sql));
        $this->assertStringContainsString('for update', strtolower((string) $sql));
    }

    public function test_order_by_not_duplicated_with_limit_and_lock()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name')->limit(10)->lock();

        $sql = $builder->toSql();

        // Should contain ORDER BY only once
        $this->assertEquals(1, substr_count(strtolower((string) $sql), 'order by'));
        $this->assertStringContainsString('order by', strtolower((string) $sql));
    }

    public function test_order_by_appended_when_not_present()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name');

        $sql = $builder->toSql();

        $this->assertStringContainsString('order by "name" asc', strtolower((string) $sql));
    }

    public function test_order_by_duplication_prevention_works_correctly()
    {
        // This test demonstrates that the ORDER BY duplication prevention
        // successfully prevents duplicate ORDER BY when both limit and lock are used
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name')->limit(10)->lock();

        $sql = $builder->toSql();

        // Should contain ORDER BY only once (the bug would cause 2)
        $this->assertEquals(1, substr_count(strtolower((string) $sql), 'order by'));
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('for update', strtolower($sql));
    }
}
