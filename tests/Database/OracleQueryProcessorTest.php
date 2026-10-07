<?php

namespace Yajra\Oci8\Tests\Database;

use DateTimeImmutable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery as m;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;

class OracleQueryProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_insert_get_id_runs_before_executing_callbacks_before_preparing_the_statement(): void
    {
        $callbackRan = false;
        $statement = m::mock(PDOStatement::class);
        $statement->shouldReceive('bindParam')->once()->with(1, m::any(), PDO::PARAM_INT, -1)
            ->andReturnUsing(function ($parameter, &$id) {
                $id = 42;

                return true;
            });
        $statement->shouldReceive('execute')->once()->andReturn(true);

        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('prepare')->once()->andReturnUsing(function ($sql) use (&$callbackRan, $statement) {
            $this->assertTrue($callbackRan);

            return $statement;
        });

        $connection = new Oci8Connection($pdo);
        $connection->enableQueryLog();
        $connection->beforeExecuting(function ($sql, $bindings, $executingConnection) use (&$callbackRan, $connection) {
            $this->assertSame($connection, $executingConnection);
            $this->assertSame([], $bindings);
            $callbackRan = true;
        });

        $this->assertSame(42, $connection->table('users')->insertGetId([]));
        $this->assertCount(1, $connection->getQueryLog());
        $this->assertTrue($connection->hasModifiedRecords());
    }

    public function test_insert_get_id_does_not_prepare_a_statement_when_pretending(): void
    {
        $pdo = m::mock(PDO::class);
        $pdo->shouldNotReceive('prepare');
        $pdo->shouldReceive('quote')->with('Taylor')->andReturn("'Taylor'");
        $connection = new Oci8Connection($pdo);

        $queries = $connection->pretend(function ($connection) {
            $this->assertSame(0, $connection->table('users')->insertGetId(['name' => 'Taylor']));
        });

        $this->assertCount(1, $queries);
        $this->assertSame(['Taylor'], $queries[0]['bindings']);
        $this->assertFalse($connection->hasModifiedRecords());
    }

    public function test_insert_get_id_prepares_bindings_and_returns_the_generated_id(): void
    {
        $statement = m::mock(PDOStatement::class);
        $statement->shouldReceive('bindValue')->once()->with(1, '2026-10-07 12:00:00', PDO::PARAM_STR);
        $statement->shouldReceive('bindValue')->once()->with(2, 1, PDO::PARAM_STR);
        $statement->shouldReceive('bindValue')->once()->with(3, 'Taylor', PDO::PARAM_STR);
        $statement->shouldReceive('bindValue')->once()->with(4, null, PDO::PARAM_STR);
        $statement->shouldReceive('bindParam')->once()->with(5, m::any(), PDO::PARAM_INT, -1)
            ->andReturnUsing(function ($parameter, &$id) {
                $id = 42;

                return true;
            });
        $statement->shouldReceive('execute')->once()->andReturn(true);

        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('prepare')->once()->andReturn($statement);
        $connection = new Oci8Connection($pdo);

        $id = $connection->table('users')->insertGetId([
            'created_at' => new DateTimeImmutable('2026-10-07 12:00:00'),
            'enabled' => true,
            'name' => 'Taylor',
            'note' => null,
        ]);

        $this->assertSame(42, $id);
    }

    public function test_insert_get_id_converts_unique_constraint_errors_from_statement_preparation(): void
    {
        $exception = new PDOException('ORA-00001: unique constraint violated');
        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('prepare')->once()->andThrow($exception);
        $connection = new Oci8Connection($pdo);

        $this->expectException(UniqueConstraintViolationException::class);

        $connection->table('users')->insertGetId([]);
    }

    public function test_explicit_sequence_uses_the_write_connection_when_sticky_is_enabled(): void
    {
        $sequenceStatement = m::mock(PDOStatement::class);
        $sequenceStatement->shouldReceive('setFetchMode')->once()->with(PDO::FETCH_OBJ);
        $sequenceStatement->shouldReceive('execute')->once()->andReturn(true);
        $sequenceStatement->shouldReceive('fetchAll')->once()->andReturn([(object) ['id' => 41]]);

        $insertStatement = m::mock(PDOStatement::class);
        $insertStatement->shouldReceive('bindValue')->once()->with(1, 'Taylor', PDO::PARAM_STR);
        $insertStatement->shouldReceive('bindValue')->once()->with(2, 41, PDO::PARAM_STR);
        $insertStatement->shouldReceive('bindParam')->once()->with(3, m::any(), PDO::PARAM_INT, -1)
            ->andReturnUsing(function ($parameter, &$id) {
                $id = 41;

                return true;
            });
        $insertStatement->shouldReceive('execute')->once()->andReturn(true);

        $writePdo = m::mock(PDO::class);
        $writePdo->shouldReceive('prepare')->once()->with('SELECT "USERS_ID_SEQ".NEXTVAL as "id" FROM DUAL')->andReturn($sequenceStatement);
        $writePdo->shouldReceive('prepare')->once()->with('insert into "USERS" ("NAME", "ID") values (?, ?) returning "ID" into ?')->andReturn($insertStatement);
        $readPdo = m::mock(PDO::class);
        $readPdo->shouldNotReceive('prepare');

        $connection = new Oci8Connection($writePdo, '', '', ['sticky' => true]);
        $connection->setReadPdo($readPdo);
        $resolver = m::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->andReturn($connection);
        Model::setConnectionResolver($resolver);

        try {
            $model = new class extends Model
            {
                public $sequence = 'users_id_seq';

                protected $table = 'users';
            };

            $this->assertSame(41, $model->newQuery()->insertGetId(['name' => 'Taylor']));
        } finally {
            Model::unsetConnectionResolver();
        }
    }
}
