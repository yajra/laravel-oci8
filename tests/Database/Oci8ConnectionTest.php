<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Mockery as m;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\Sequence;
use Yajra\Oci8\Schema\Trigger;

class Oci8ConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_get_schema()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getConfig')->with('username')->andReturn('demo');
        $connection->shouldReceive('getSchema')->andReturn('demo');

        $this->assertSame('demo', $connection->getConfig('username'));
    }

    public function test_set_schema()
    {
        $pdo = new Oci8ConnectionTestMockPDO;
        $connection = new Oci8Connection($pdo, config: ['username' => 'original']);

        $this->assertSame($connection, $connection->setSchema('demo'));
        $this->assertSame('demo', $connection->getSchema());
        $this->assertSame('ALTER SESSION SET CURRENT_SCHEMA  = demo', $pdo->lastPreparedSql);
    }

    public function test_get_schema_prefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSchemaPrefix')->andReturn('schema_prefix');

        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function test_set_schema_prefix()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('setSchemaPrefix')->with('schema_prefix')->once()->andReturn($connection);
        $connection->shouldReceive('getSchemaPrefix')->once()->andReturn('schema_prefix');

        $connection->setSchemaPrefix('schema_prefix');
        $this->assertSame('schema_prefix', $connection->getSchemaPrefix());
    }

    public function test_get_trigger()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getTrigger')->once()->andReturn(new Trigger($connection));

        $this->assertInstanceOf(Trigger::class, $connection->getTrigger());
    }

    public function test_get_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('getSequence')->once()->andReturn(new Sequence($connection));

        $this->assertInstanceOf(Sequence::class, $connection->getSequence());
    }

    public function test_create_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with('posts_id_seq')->once()->andReturn(true);
        $this->assertTrue($connection->createSequence('posts_id_seq'));
    }

    public function test_create_sequence_invalid_name()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('createSequence')->with(null)->once()->andReturn(false);
        $this->assertFalse($connection->createSequence(null));
    }

    public function test_drop_sequence()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->shouldReceive('checkSequence')->with('posts_id_seq')->once()->andReturn(true);
        $connection->checkSequence('posts_id_seq');
        $this->assertTrue($connection->dropSequence('posts_id_seq'));
    }

    public function test_drop_sequence_invalid_name()
    {
        $connection = m::mock(Oci8Connection::class);
        $connection->shouldReceive('dropSequence')->with(null)->once()->andReturn(false);
        $connection->shouldReceive('checkSequence')->with(null)->once()->andReturn(true);
        $connection->checkSequence(null);
        $this->assertFalse($connection->dropSequence(null));
    }

    public function test_get_server_version()
    {
        $pdo = new Oci8ConnectionTestMockPDO;
        $pdo->statement = new Oci8ConnectionTestMockPDOStatement((object) [
            'banner' => 'Oracle Database 19c Enterprise Edition',
        ]);

        $connection = new Oci8Connection($pdo);

        $this->assertSame('Oracle Database 19c Enterprise Edition', $connection->getServerVersion());
        $this->assertSame('SELECT BANNER FROM v$version', $pdo->lastPreparedSql);
        $this->assertTrue($pdo->statement->executed);
    }

    public function test_get_date_format_returns_configured_session_format()
    {
        $connection = new Oci8Connection(new Oci8ConnectionTestMockPDO, config: [
            'sessionVars' => [
                'NLS_DATE_FORMAT' => 'YYYY-MM-DD',
            ],
        ]);

        $this->assertSame('YYYY-MM-DD', $connection->getDateFormat());
    }

    public function test_get_date_format_returns_default_when_session_format_is_missing()
    {
        $connection = new Oci8Connection(new Oci8ConnectionTestMockPDO);

        $this->assertSame('YYYY-MM-DD HH24:MI:SS', $connection->getDateFormat());
    }

    public function test_thread_count_returns_null_when_oracle_session_view_is_not_accessible()
    {
        $pdo = new Oci8ConnectionTestMockPDO;
        $pdo->prepareException = new PDOException('ORA-00942: table or view "SYS"."V_$SESSION" does not exist');

        $connection = new Oci8Connection($pdo);

        $this->assertNull($connection->threadCount());
        $this->assertSame('select count(*) as "Value" from v$session', $pdo->lastPreparedSql);
    }

    public function test_thread_count_rethrows_non_session_view_query_exceptions()
    {
        $pdo = new Oci8ConnectionTestMockPDO;
        $pdo->prepareException = new PDOException('ORA-01031: insufficient privileges');

        $connection = new Oci8Connection($pdo);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('ORA-01031: insufficient privileges');

        $connection->threadCount();
    }

    public function test_get_configured_server_version()
    {
        $connection = m::mock(Oci8Connection::class)->makePartial();
        $connection->shouldReceive('getConfig')->with('server_version')->once()->andReturn('19c');

        $this->assertSame('19c', $connection->serverVersion());
    }

    public function test_compare_configured_server_version()
    {
        $connection = m::mock(Oci8Connection::class)->makePartial();
        $connection->shouldReceive('getConfig')->with('server_version')->times(4)->andReturn('19c');

        $this->assertTrue($connection->isVersionAbove('12c'));
        $this->assertTrue($connection->isVersionAboveOrEqual('19c'));
        $this->assertTrue($connection->isVersionBelow('21c'));
        $this->assertTrue($connection->isVersionBelowOrEqual('19c'));
    }

    public function test_compare_configured_12cr2_server_version()
    {
        $connection = m::mock(Oci8Connection::class)->makePartial();
        $connection->shouldReceive('getConfig')->with('server_version')->times(6)->andReturn('12cR2');

        $this->assertTrue($connection->isVersionAbove('11g'));
        $this->assertTrue($connection->isVersionAbove('12c'));
        $this->assertTrue($connection->isVersionAboveOrEqual('12cR2'));
        $this->assertFalse($connection->isVersionBelow('11g'));
        $this->assertTrue($connection->isVersionBelow('19c'));
        $this->assertTrue($connection->isVersionBelowOrEqual('12cR2'));
    }

    protected function getMockConnection($methods = [], $pdo = null)
    {
        $pdo = $pdo ?: new DatabaseConnectionTestMockPDO;
        $defaults = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(array_merge($defaults, $methods))
            ->setConstructorArgs([$pdo])
            ->getMock();
        $connection->enableQueryLog();

        return $connection;
    }
}

class DatabaseConnectionTestMockPDO extends PDO
{
    public function __construct() {}
}

class Oci8ConnectionTestMockPDO extends PDO
{
    public ?string $lastPreparedSql = null;

    public ?PDOException $prepareException = null;

    public Oci8ConnectionTestMockPDOStatement $statement;

    public function __construct()
    {
        $this->statement = new Oci8ConnectionTestMockPDOStatement;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->lastPreparedSql = $query;

        if ($this->prepareException) {
            throw $this->prepareException;
        }

        return $this->statement;
    }
}

class Oci8ConnectionTestMockPDOStatement extends PDOStatement
{
    public bool $executed = false;

    public function __construct(private ?object $fetchResult = null) {}

    public function execute(?array $params = null): bool
    {
        $this->executed = true;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->fetchResult;
    }

    public function setFetchMode(int $mode, mixed ...$args): true
    {
        return true;
    }
}
