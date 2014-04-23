<?php

use Mockery as m;
use yajra\Oci8\Query\OracleBuilder as Builder;
use Illuminate\Database\Query\Expression as Raw;

class Oci8QueryBuilderTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}

	public function testBasicSelect()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users');
		$this->assertEquals('select * from users', $builder->toSql());
	}

	public function testAddingSelects()
	{
		$builder = $this->getBuilder();
		$builder->select('foo')->addSelect('bar')->addSelect(array('baz', 'boom'))->from('users');
		$this->assertEquals('select foo, bar, baz, boom from users', $builder->toSql());
	}

	public function testBasicSelectWithPrefix()
	{
		$builder = $this->getBuilder();
		$builder->getGrammar()->setTablePrefix('prefix_');
		$builder->select('*')->from('users');
		$this->assertEquals('select * from prefix_users', $builder->toSql());
	}

	public function testBasicSelectDistinct()
	{
		$builder = $this->getBuilder();
		$builder->distinct()->select('foo', 'bar')->from('users');
		$this->assertEquals('select distinct foo, bar from users', $builder->toSql());
	}

	public function testSelectWithCaching()
	{
		$cache = m::mock('stdClass');
		$driver = m::mock('stdClass');
		$query = $this->setupCacheTestQuery($cache, $driver);

		$query = $query->remember(5);

		$driver->shouldReceive('remember')
			 ->once()
			 ->with($query->getCacheKey(), 5, m::type('Closure'))
			 ->andReturnUsing(function($key, $minutes, $callback) { return $callback(); });


		$this->assertEquals($query->get(), array('results'));
	}

	public function testSelectWithCachingForever()
	{
		$cache = m::mock('stdClass');
		$driver = m::mock('stdClass');
		$query = $this->setupCacheTestQuery($cache, $driver);

		$query = $query->rememberForever();

		$driver->shouldReceive('rememberForever')
			->once()
			->with($query->getCacheKey(), m::type('Closure'))
			->andReturnUsing(function($key, $callback) { return $callback(); });



		$this->assertEquals($query->get(), array('results'));
	}

	public function testSelectWithCachingAndTags()
	{
		$taggedCache = m::mock('StdClass');
		$cache = m::mock('stdClass');
		$driver = m::mock('stdClass');

		$driver->shouldReceive('tags')
				->once()
				->with(array('foo','bar'))
				->andReturn($taggedCache);

		$query = $this->setupCacheTestQuery($cache, $driver);
		$query = $query->cacheTags(array('foo', 'bar'))->remember(5);

		$taggedCache->shouldReceive('remember')
						->once()
						->with($query->getCacheKey(), 5, m::type('Closure'))
						->andReturnUsing(function($key, $minutes, $callback) { return $callback(); });

		$this->assertEquals($query->get(), array('results'));
	}

	public function testBasicAlias()
	{
		$builder = $this->getBuilder();
		$builder->select('foo as bar')->from('users');
		$this->assertEquals('select foo as bar from users', $builder->toSql());
	}

	public function testBasicTableWrapping()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('public.users');
		$this->assertEquals('select * from public.users', $builder->toSql());
	}

	public function testBasicWheres()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1);
		$this->assertEquals('select * from users where id = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1), $builder->getBindings());
	}

	public function testWhereBetweens()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereBetween('id', array(1, 2));
		$this->assertEquals('select * from users where id between ? and ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereNotBetween('id', array(1, 2));
		$this->assertEquals('select * from users where id not between ? and ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2), $builder->getBindings());
	}

	public function testBasicOrWheres()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
		$this->assertEquals('select * from users where id = ? or email = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 'foo'), $builder->getBindings());
	}

	public function testRawWheres()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereRaw('id = ? or email = ?', array(1, 'foo'));
		$this->assertEquals('select * from users where id = ? or email = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 'foo'), $builder->getBindings());
	}

	public function testRawOrWheres()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', array('foo'));
		$this->assertEquals('select * from users where id = ? or email = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 'foo'), $builder->getBindings());
	}

	public function testBasicWhereIns()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereIn('id', array(1, 2, 3));
		$this->assertEquals('select * from users where id in (?, ?, ?)', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2, 2 => 3), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', array(1, 2, 3));
		$this->assertEquals('select * from users where id = ? or id in (?, ?, ?)', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 1, 2 => 2, 3 => 3), $builder->getBindings());
	}

	public function testBasicWhereNotIns()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereNotIn('id', array(1, 2, 3));
		$this->assertEquals('select * from users where id not in (?, ?, ?)', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2, 2 => 3), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', array(1, 2, 3));
		$this->assertEquals('select * from users where id = ? or id not in (?, ?, ?)', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 1, 2 => 2, 3 => 3), $builder->getBindings());
	}

	public function testUnions()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1);
		$builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
		$this->assertEquals('select * from users where id = ? union select * from users where id = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2), $builder->getBindings());
	}

	public function testUnionAlls()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1);
		$builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
		$this->assertEquals('select * from users where id = ? union all select * from users where id = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2), $builder->getBindings());
	}

	public function testMultipleUnions()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1);
		$builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
		$builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
		$this->assertEquals('select * from users where id = ? union select * from users where id = ? union select * from users where id = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2, 2 => 3), $builder->getBindings());
	}

	public function testMultipleUnionAlls()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1);
		$builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
		$builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
		$this->assertEquals('select * from users where id = ? union all select * from users where id = ? union all select * from users where id = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 2, 2 => 3), $builder->getBindings());
	}

	public function testSubSelectWhereIns()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereIn('id', function($q)
		{
			$q->select('id')->from('users')->where('age', '>', 25)->take(3);
		});
		$this->assertEquals('select * from users where id in (select t2.* from ( select rownum AS "rn", t1.* from (select id from users where age > ?) t1 ) t2 where t2."rn" between 1 and 3)', $builder->toSql());
                $this->assertEquals(array(25), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereNotIn('id', function($q)
		{
			$q->select('id')->from('users')->where('age', '>', 25)->take(3);
		});
		$this->assertEquals('select * from users where id not in (select t2.* from ( select rownum AS "rn", t1.* from (select id from users where age > ?) t1 ) t2 where t2."rn" between 1 and 3)', $builder->toSql());
		$this->assertEquals(array(25), $builder->getBindings());
	}

	public function testBasicWhereNulls()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereNull('id');
		$this->assertEquals('select * from users where id is null', $builder->toSql());
		$this->assertEquals(array(), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
		$this->assertEquals('select * from users where id = ? or id is null', $builder->toSql());
		$this->assertEquals(array(0 => 1), $builder->getBindings());
	}

	public function testBasicWhereNotNulls()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->whereNotNull('id');
		$this->assertEquals('select * from users where id is not null', $builder->toSql());
		$this->assertEquals(array(), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
		$this->assertEquals('select * from users where id > ? or id is not null', $builder->toSql());
		$this->assertEquals(array(0 => 1), $builder->getBindings());
	}

	public function testGroupBys()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->groupBy('id', 'email');
		$this->assertEquals('select * from users group by id, email', $builder->toSql());
	}

	public function testOrderBys()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
		$this->assertEquals('select * from users order by email asc, age desc', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->orderBy('email')->orderByRaw('age ? desc', array('bar'));
		$this->assertEquals('select * from users order by email asc, age ? desc', $builder->toSql());
		$this->assertEquals(array('bar'), $builder->getBindings());
	}

	public function testHavings()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->having('email', '>', 1);
		$this->assertEquals('select * from users having email > ?', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->groupBy('email')->having('email', '>', 1);
		$this->assertEquals('select * from users group by email having email > ?', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('email as foo_email')->from('users')->having('foo_email', '>', 1);
		$this->assertEquals('select email as foo_email from users having foo_email > ?', $builder->toSql());
	}

	public function testRawHavings()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
		$this->assertEquals('select * from users having user_foo < user_bar', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
		$this->assertEquals('select * from users having baz = ? or user_foo < user_bar', $builder->toSql());
	}

	public function testLimitsAndOffsets()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->offset(10);
		$this->assertEquals('select * from (select * from users) where rownum >= 11', $builder->toSql());

		$builder = $this->getBuilder();
 		$builder->select('*')->from('users')->offset(5)->limit(10);
		$this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from users) t1 ) t2 where t2."rn" between 6 and 15', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->skip(5)->take(10);
		$this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from users) t1 ) t2 where t2."rn" between 6 and 15', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->skip(-5)->take(10);
		$this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from users) t1 ) t2 where t2."rn" between 1 and 10', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->forPage(2, 15);
		$this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from users) t1 ) t2 where t2."rn" between 16 and 30', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->forPage(-2, 15);
		$this->assertEquals('select t2.* from ( select rownum AS "rn", t1.* from (select * from users) t1 ) t2 where t2."rn" between 1 and 15', $builder->toSql());
	}

	public function testWhereShortcut()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
		$this->assertEquals('select * from users where id = ? or name = ?', $builder->toSql());
		$this->assertEquals(array(0 => 1, 1 => 'foo'), $builder->getBindings());
	}

	public function testNestedWheres()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function($q)
		{
			$q->where('name', '=', 'bar')->where('age', '=', 25);
		});
		$this->assertEquals('select * from users where email = ? or (name = ? and age = ?)', $builder->toSql());
		$this->assertEquals(array(0 => 'foo', 1 => 'bar', 2 => 25), $builder->getBindings());
	}

	public function testFullSubSelects()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function($q)
		{
			$q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
		});

		$this->assertEquals('select * from users where email = ? or id = (select max(id) from users where email = ?)', $builder->toSql());
		$this->assertEquals(array(0 => 'foo', 1 => 'bar'), $builder->getBindings());
	}

	public function testWhereExists()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('orders')->whereExists(function($q)
		{
			$q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
		});
		$this->assertEquals('select * from orders where exists (select * from products where products.id = orders.id)', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('orders')->whereNotExists(function($q)
		{
			$q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
		});
		$this->assertEquals('select * from orders where not exists (select * from products where products.id = orders.id)', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(function($q)
		{
			$q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
		});
		$this->assertEquals('select * from orders where id = ? or exists (select * from products where products.id = orders.id)', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(function($q)
		{
			$q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
		});
		$this->assertEquals('select * from orders where id = ? or not exists (select * from products where products.id = orders.id)', $builder->toSql());
	}

	public function testBasicJoins()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->join('contacts', 'users.id', '=', 'contacts.id')->leftJoin('photos', 'users.id', '=', 'photos.id');
		$this->assertEquals('select * from users inner join contacts on users.id = contacts.id left join photos on users.id = photos.id', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->leftJoinWhere('photos', 'users.id', '=', 'bar')->joinWhere('photos', 'users.id', '=', 'foo');
		$this->assertEquals('select * from users left join photos on users.id = ? inner join photos on users.id = ?', $builder->toSql());
		$this->assertEquals(array('bar', 'foo'), $builder->getBindings());
	}

	public function testComplexJoin()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->join('contacts', function($j)
		{
			$j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
		});
		$this->assertEquals('select * from users inner join contacts on users.id = contacts.id or users.name = contacts.name', $builder->toSql());

		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->join('contacts', function($j)
		{
			$j->where('users.id', '=', 'foo')->orWhere('users.name', '=', 'bar');
		});
		$this->assertEquals('select * from users inner join contacts on users.id = ? or users.name = ?', $builder->toSql());
		$this->assertEquals(array('foo', 'bar'), $builder->getBindings());
	}

	public function testRawExpressionsInSelect()
	{
		$builder = $this->getBuilder();
		$builder->select(new Raw('substr(foo, 6)'))->from('users');
		$this->assertEquals('select substr(foo, 6) from users', $builder->toSql());
	}

	public function testFindReturnsFirstResultByID()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select t2.* from ( select rownum AS "rn", t1.* from (select * from users where id = ?) t1 ) t2 where t2."rn" between 1 and 1', array(1))->andReturn(array(array('foo' => 'bar')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('foo' => 'bar')))->andReturnUsing(function($query, $results) { return $results; });
		$results = $builder->from('users')->find(1);
		$this->assertEquals(array('foo' => 'bar'), $results);
	}

	public function testFirstMethodReturnsFirstResult()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select t2.* from ( select rownum AS "rn", t1.* from (select * from users where id = ?) t1 ) t2 where t2."rn" between 1 and 1', array(1))->andReturn(array(array('foo' => 'bar')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('foo' => 'bar')))->andReturnUsing(function($query, $results) { return $results; });
		$results = $builder->from('users')->where('id', '=', 1)->first();
		$this->assertEquals(array('foo' => 'bar'), $results);
	}

	public function testListMethodsGetsArrayOfColumnValues()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->andReturn(array(array('foo' => 'bar'), array('foo' => 'baz')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('foo' => 'bar'), array('foo' => 'baz')))->andReturnUsing(function($query, $results)
		{
			return $results;
		});
		$results = $builder->from('users')->where('id', '=', 1)->lists('foo');
		$this->assertEquals(array('bar', 'baz'), $results);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->andReturn(array(array('id' => 1, 'foo' => 'bar'), array('id' => 10, 'foo' => 'baz')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('id' => 1, 'foo' => 'bar'), array('id' => 10, 'foo' => 'baz')))->andReturnUsing(function($query, $results)
		{
			return $results;
		});
		$results = $builder->from('users')->where('id', '=', 1)->lists('foo', 'id');
		$this->assertEquals(array(1 => 'bar', 10 => 'baz'), $results);
	}

	public function testImplode()
	{
		// Test without glue.
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->andReturn(array(array('foo' => 'bar'), array('foo' => 'baz')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('foo' => 'bar'), array('foo' => 'baz')))->andReturnUsing(function($query, $results)
		{
			return $results;
		});
		$results = $builder->from('users')->where('id', '=', 1)->implode('foo');
		$this->assertEquals('barbaz', $results);

		// Test with glue.
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->andReturn(array(array('foo' => 'bar'), array('foo' => 'baz')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('foo' => 'bar'), array('foo' => 'baz')))->andReturnUsing(function($query, $results)
		{
			return $results;
		});
		$results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
		$this->assertEquals('bar,baz', $results);
	}

	public function testPaginateCorrectlyCreatesPaginatorInstance()
	{
		$connection = m::mock('Illuminate\Database\ConnectionInterface');
		$grammar = m::mock('Illuminate\Database\Query\Grammars\Grammar');
		$processor = m::mock('Illuminate\Database\Query\Processors\Processor');
		$builder = $this->getMock('Illuminate\Database\Query\Builder', array('getPaginationCount', 'forPage', 'get'), array($connection, $grammar, $processor));
		$paginator = m::mock('Illuminate\Pagination\Environment');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(1);
		$connection->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$builder->expects($this->once())->method('forPage')->with($this->equalTo(1), $this->equalTo(15))->will($this->returnValue($builder));
		$builder->expects($this->once())->method('get')->with($this->equalTo(array('*')))->will($this->returnValue(array('foo')));
		$builder->expects($this->once())->method('getPaginationCount')->will($this->returnValue(10));
		$paginator->shouldReceive('make')->once()->with(array('foo'), 10, 15)->andReturn(array('results'));

		$this->assertEquals(array('results'), $builder->paginate(15, array('*')));
	}

	public function testPaginateCorrectlyCreatesPaginatorInstanceForGroupedQuery()
	{
		$connection = m::mock('Illuminate\Database\ConnectionInterface');
		$grammar = m::mock('Illuminate\Database\Query\Grammars\Grammar');
		$processor = m::mock('Illuminate\Database\Query\Processors\Processor');
		$builder = $this->getMock('Illuminate\Database\Query\Builder', array('get'), array($connection, $grammar, $processor));
		$paginator = m::mock('Illuminate\Pagination\Environment');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(2);
		$connection->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$builder->expects($this->once())->method('get')->with($this->equalTo(array('*')))->will($this->returnValue(array('foo', 'bar', 'baz')));
		$paginator->shouldReceive('make')->once()->with(array('baz'), 3, 2)->andReturn(array('results'));

		$this->assertEquals(array('results'), $builder->groupBy('foo')->paginate(2, array('*')));
	}

	public function testGetPaginationCountGetsResultCount()
	{
		unset($_SERVER['orders']);
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($query, $results)
		{
			$_SERVER['orders'] = $query->orders;
			return $results;
		});
		$results = $builder->from('users')->orderBy('foo', 'desc')->getPaginationCount();

		$this->assertNull($_SERVER['orders']);
		unset($_SERVER['orders']);

		$this->assertEquals(array(0 => array('column' => 'foo', 'direction' => 'desc')), $builder->orders);
		$this->assertEquals(1, $results);
	}

	public function testPluckMethodReturnsSingleColumn()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select t2.* from ( select rownum AS "rn", t1.* from (select foo from users where id = ?) t1 ) t2 where t2."rn" between 1 and 1', array(1))->andReturn(array(array('rn' => 1, 'foo' => 'bar')));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, array(array('rn' => 1, 'foo' => 'bar')))->andReturn(array(array('rn' => 1, 'foo' => 'bar')));
		$results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
		$this->assertEquals('bar', $results);
	}

	public function testAggregateFunctions()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($builder, $results) { return $results; });
		$results = $builder->from('users')->count();
		$this->assertEquals(1, $results);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select count(*) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($builder, $results) { return $results; });
		$results = $builder->from('users')->exists();
		$this->assertTrue($results);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select max(id) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($builder, $results) { return $results; });
		$results = $builder->from('users')->max('id');
		$this->assertEquals(1, $results);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select min(id) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($builder, $results) { return $results; });
		$results = $builder->from('users')->min('id');
		$this->assertEquals(1, $results);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('select')->once()->with('select sum(id) as aggregate from users', array())->andReturn(array(array('aggregate' => 1)));
		$builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function($builder, $results) { return $results; });
		$results = $builder->from('users')->sum('id');
		$this->assertEquals(1, $results);
	}

	public function testInsertMethod()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('insert')->once()->with('insert into users (email) values (?)', array('foo'))->andReturn(true);
		$result = $builder->from('users')->insert(array('email' => 'foo'));
		$this->assertTrue($result);
	}

	public function testMultipleInsertMethod()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('insert')->once()->with('insert into users (email) select ? from dual union all select ? from dual ', array('foo','foo'))->andReturn(true);
		$data[] = array('email'=>'foo');
		$data[] = array('email'=>'foo');
		$result = $builder->from('users')->insert($data);
		$this->assertTrue($result);
	}

	public function testInsertGetIdMethod()
	{
		$builder = $this->getBuilder();
		$builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into users (email) values (?) returning id into ?', array('foo'), 'id')->andReturn(1);
		$result = $builder->from('users')->insertGetId(array('email' => 'foo'), 'id');
		$this->assertEquals(1, $result);
	}

	public function testInsertLobMethod()
	{
		$builder = $this->getBuilder();
		$builder->getProcessor()->shouldReceive('saveLob')->once()->with($builder, 'insert into users (email, blob) values (?, EMPTY_BLOB()) returning blob, id into ?, ?', array('foo'), array('test data'))->andReturn(1);
		$result = $builder->from('users')->insertLob(array('email' => 'foo'), array('blob' => 'test data'), 'id');
		$this->assertEquals(1, $result);
	}

	/* @todo: fix test failing on PHP5.4++
	public function testUpdateLobMethod()
	{
		$builder = $this->getBuilder();
		$builder->getProcessor()->shouldReceive('saveLob')->once()->with($builder, 'update users set email = ? where id = ? returning blob, id into ?, ?', array('foo',1), array('test data'))->andReturn(1);
		$result = $builder->from('users')->where('id','=',1)->updateLob(array('email' => 'foo'), array('blob' => 'test data'), 'id');
		$this->assertEquals(1, $result);
	}
	*/

	public function testInsertGetIdMethodRemovesExpressions()
	{
		$builder = $this->getBuilder();
		$builder->getProcessor()->shouldReceive('processInsertGetId')->once()->with($builder, 'insert into users (email, bar) values (?, bar) returning id into ?', array('foo'), 'id')->andReturn(1);
		$result = $builder->from('users')->insertGetId(array('email' => 'foo', 'bar' => new Illuminate\Database\Query\Expression('bar')), 'id');
		$this->assertEquals(1, $result);
	}

	public function testInsertMethodRespectsRawBindings()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('insert')->once()->with('insert into users (email) values (CURRENT TIMESTAMP)', array())->andReturn(true);
		$result = $builder->from('users')->insert(array('email' => new Raw('CURRENT TIMESTAMP')));
		$this->assertTrue($result);
	}

	public function testUpdateMethod()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('update')->once()->with('update users set email = ?, name = ? where id = ?', array('foo', 'bar', 1))->andReturn(1);
		$result = $builder->from('users')->where('id', '=', 1)->update(array('email' => 'foo', 'name' => 'bar'));
		$this->assertEquals(1, $result);
	}

	public function testUpdateMethodWithJoins()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('update')->once()->with('update users inner join orders on users.id = orders.user_id set email = ?, name = ? where users.id = ?', array('foo', 'bar', 1))->andReturn(1);
		$result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(array('email' => 'foo', 'name' => 'bar'));
		$this->assertEquals(1, $result);
	}

	public function testUpdateMethodRespectsRaw()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('update')->once()->with('update users set email = foo, name = ? where id = ?', array('bar', 1))->andReturn(1);
		$result = $builder->from('users')->where('id', '=', 1)->update(array('email' => new Raw('foo'), 'name' => 'bar'));
		$this->assertEquals(1, $result);
	}

	public function testDeleteMethod()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('delete')->once()->with('delete from users where email = ?', array('foo'))->andReturn(1);
		$result = $builder->from('users')->where('email', '=', 'foo')->delete();
		$this->assertEquals(1, $result);

		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('delete')->once()->with('delete from users where id = ?', array(1))->andReturn(1);
		$result = $builder->from('users')->delete(1);
		$this->assertEquals(1, $result);
	}

	/** @todo truncate failing ...
	public function testTruncateMethod()
	{
		$builder = $this->getBuilder();
		$builder->getConnection()->shouldReceive('statement')->once()->with('truncate users', array());
		$builder->from('users')->truncate();
	}
	*/

	public function testMergeWheresCanMergeWheresAndBindings()
	{
		$builder = $this->getBuilder();
		$builder->wheres = array('foo');
		$builder->mergeWheres(array('wheres'), array(12 => 'foo', 13 => 'bar'));
		$this->assertEquals(array('foo', 'wheres'), $builder->wheres);
		$this->assertEquals(array('foo', 'bar'), $builder->getBindings());
	}

	public function testProvidingNullOrFalseAsSecondParameterBuildsCorrectly()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('users')->where('foo', null);
		$this->assertEquals('select * from users where foo is null', $builder->toSql());
	}

	public function testDynamicWhere()
	{
		$method     = 'whereFooBarAndBazOrQux';
		$parameters = array('corge', 'waldo', 'fred');
		$grammar = new yajra\Oci8\Query\Grammars\OracleGrammar;
		$processor = m::mock('yajra\Oci8\Query\Processors\OracleProcessor');
		$builder    = m::mock('Illuminate\Database\Query\Builder[where]', array(m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor));

		$builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturn($builder);
		$builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturn($builder);
		$builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturn($builder);

		$this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));

	}

	public function testDynamicWhereIsNotGreedy()
	{
		$method     = 'whereIosVersionAndAndroidVersionOrOrientation';
		$parameters = array('6.1', '4.2', 'Vertical');
		$grammar = new yajra\Oci8\Query\Grammars\OracleGrammar;
		$processor = m::mock('yajra\Oci8\Query\Processors\OracleProcessor');
		$builder    = m::mock('Illuminate\Database\Query\Builder[where]', array(m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor));

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

	public function setupCacheTestQuery($cache, $driver)
	{
		$connection = m::mock('Illuminate\Database\ConnectionInterface');
		$connection->shouldReceive('getName')->andReturn('connection_name');
		$connection->shouldReceive('getCacheManager')->once()->andReturn($cache);
		$cache->shouldReceive('driver')->once()->andReturn($driver);
		$grammar = new yajra\Oci8\Query\Grammars\OracleGrammar;
		$processor = m::mock('yajra\Oci8\Query\Processors\OracleProcessor');

		$builder = $this->getMock('Illuminate\Database\Query\Builder', array('getFresh'), array($connection, $grammar, $processor));
		$builder->expects($this->once())->method('getFresh')->with($this->equalTo(array('*')))->will($this->returnValue(array('results')));
		return $builder->select('*')->from('users')->where('email', 'foo@bar.com');
	}

	public function testOracleLock()
	{
		$builder = $this->getBuilder();
		$builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
		$this->assertEquals('select * from foo where bar = ? for update', $builder->toSql());
		$this->assertEquals(array('baz'), $builder->getBindings());

		$builder = $this->getBuilder();
		$builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
		$this->assertEquals('select * from foo where bar = ? lock in share mode', $builder->toSql());
		$this->assertEquals(array('baz'), $builder->getBindings());
	}

	protected function getBuilder()
	{
		$grammar = new yajra\Oci8\Query\Grammars\OracleGrammar;
		$processor = m::mock('yajra\Oci8\Query\Processors\OracleProcessor');
		return new Builder(m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor);
	}

}
