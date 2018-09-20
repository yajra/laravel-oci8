<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Pdo\Oci8\Exceptions\Oci8Exception;
use Yajra\Oci8\Query\OracleBuilder as Builder;
use Illuminate\Database\Query\Expression as Raw;

class Oci8QueryBuilderTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "USERS"', $builder->toSql());
    }

    protected function getBuilder()
    {
        $grammar   = new Yajra\Oci8\Query\Grammars\OracleGrammar;
        $processor = m::mock(Yajra\Oci8\Query\Processors\OracleProcessor::class);

        return new Builder(m::mock(Illuminate\Database\ConnectionInterface::class), $grammar, $processor);
    }

    public function testBasicSelectWithReservedWords()
    {
        $builder = $this->getBuilder();
        $builder->select('exists', 'drop', 'group')->from('users');
        $this->assertEquals('select "EXISTS", "DROP", "GROUP" from "USERS"', $builder->toSql());
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

    public function testBasicAlias()
    {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select "FOO" as "BAR" from "USERS"', $builder->toSql());
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

    public function testWhereBetween()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertEquals('select * from "USERS" where "ID" between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
        $this->assertEquals('select * from "USERS" where "ID" not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
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

    public function testUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "USERS" where "ID" = ? union select * from "USERS" where "ID" = ?',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "USERS" where "ID" = ? union all select * from "USERS" where "ID" = ?',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMultipleUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "USERS" where "ID" = ? union select * from "USERS" where "ID" = ? union select * from "USERS" where "ID" = ?',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testMultipleUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "USERS" where "ID" = ? union all select * from "USERS" where "ID" = ? union all select * from "USERS" where "ID" = ?',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testSubSelectWhereIns()
    {
        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "USERS" where "ID" in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 ) t2 where t2."rn" between 1 and 3)',
            $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');

        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "USERS" where "ID" not in (select t2.* from ( select rownum AS "rn", t1.* from (select "ID" from "USERS" where "AGE" > ?) t1 ) t2 where t2."rn" between 1 and 3)',
            $builder->toSql());
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

    public function testGroupBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertEquals('select * from "USERS" group by "ID", "EMAIL"', $builder->toSql());
    }

    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from "USERS" order by "EMAIL" asc, "AGE" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('age ? desc', ['bar']);
        $this->assertEquals('select * from "USERS" order by "EMAIL" asc, age ? desc', $builder->toSql());
        $this->assertEquals(['bar'], $builder->getBindings());
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
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->offset(10);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" >= 11', $builder->toSql());
    }

    public function testLimitsAndOffsets()
    {
        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 6 and 15',
            $builder->toSql());

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->skip(5)->take(10);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 6 and 15',
            $builder->toSql());

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->skip(-5)->take(10);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 10',
            $builder->toSql());

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 16 and 30',
            $builder->toSql());

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 15',
            $builder->toSql());
    }

    public function testLimitAndOffsetToPaginateOne()
    {
        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->offset(0)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 1 and 1',
            $builder->toSql()
        );

        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $builder->select('*')->from('users')->offset(1)->limit(1);
        $this->assertEquals(
            'select t2.* from ( select rownum AS "rn", t1.* from (select * from "USERS") t1 ) t2 where t2."rn" between 2 and 2',
            $builder->toSql()
        );
    }

    public function testWhereShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
        $this->assertEquals('select * from "USERS" where "ID" = ? or "NAME" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
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

    public function testRawExpressionsInSelect()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');
        $this->assertEquals('select substr(foo, 6) from "USERS"', $builder->toSql());
    }

    public function testFindReturnsFirstResultByID()
    {
        $builder    = $this->getBuilder();
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
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
        $connection = $builder->getConnection();
        $connection->shouldReceive('getConfig')->andReturn('');
        $connection->shouldReceive('select')
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

    public function testImplode()
    {
        // Test without glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
        $this->assertEquals('barbaz', $results);

        // Test with glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()
                ->shouldReceive('processSelect')
                ->once()
                ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
                ->andReturnUsing(function ($query, $results) {
                    return $results;
                });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
        $this->assertEquals('bar,baz', $results);
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
                          ->insertGetId(['email' => 'foo', 'bar' => new Illuminate\Database\Query\Expression('bar')],
                              'id');
        $this->assertEquals(1, $result);
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
        $grammar    = new Yajra\Oci8\Query\Grammars\OracleGrammar;
        $processor  = m::mock('Yajra\Oci8\Query\Processors\OracleProcessor');
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
        $grammar    = new Yajra\Oci8\Query\Grammars\OracleGrammar;
        $processor  = m::mock('Yajra\Oci8\Query\Processors\OracleProcessor');
        $builder    = m::mock('Illuminate\Database\Query\Builder[where]',
            [m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor]);

        $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturn($builder);

        $builder->dynamicWhere($method, $parameters);
    }

    public function testCallTriggersDynamicWhere()
    {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testBuilderThrowsExpectedExceptionWithUndefinedMethod()
    {
        $builder = $this->getBuilder();

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

    public function testWhereDate()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-20');
        $this->assertEquals('select * from "USERS" where trunc("CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-20'], $builder->getBindings());
    }

    public function testWhereDay()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 20);
        $this->assertEquals('select * from "USERS" where extract (day from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 20], $builder->getBindings());
    }

    public function testWhereMonth()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 12);
        $this->assertEquals('select * from "USERS" where extract (month from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());
    }

    public function testWhereYear()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2015);
        $this->assertEquals('select * from "USERS" where extract (year from "CREATED_AT") = ?', $builder->toSql());
        $this->assertEquals([0 => 2015], $builder->getBindings());
    }

    public function xtestAggregateResetFollowedByGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select count(*) as aggregate from "users"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select sum("id") as aggregate from "users"', [], true)
                ->andReturn([['aggregate' => 2]]);
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select "column1", "column2" from "users"', [], true)
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

    public function xtestAggregateResetFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select count("column1") as aggregate from "users"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select "column2", "column3" from "users"', [], true)
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

    public function xtestAggregateResetFollowedByGetWithColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select count("column1") as aggregate from "users"', [], true)
                ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
                ->shouldReceive('select')
                ->once()
                ->with('select "column2", "column3" from "users"', [], true)
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

    public function testOracleUnionOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('select * from "USERS" where "ID" = ? union select * from "USERS" where "ID" = ? order by "ID" desc',
            $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }
}
