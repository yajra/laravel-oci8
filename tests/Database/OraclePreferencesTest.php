<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Schema\Blueprint;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection as Connection;
use Yajra\Oci8\Schema\Grammars\OracleGrammar;
use Yajra\Oci8\Schema\OracleBuilder;
use Yajra\Oci8\Schema\OraclePreferences;

class OraclePreferencesTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_create_preferences_with_single_full_text()
    {
        $connection = $this->getConnection();
        $oraclePreferences = new OraclePreferences($connection);

        $connection->shouldReceive('statement')
            ->andReturnUsing(function () {
                $this->assertTrue(false, 'A single full-text column cannot create preferences.');
            });

        $blueprint = new Blueprint($connection, 'users');
        $blueprint->fullText('name', 'name_search_full_text');

        $oraclePreferences->createPreferences($blueprint);

        $this->assertTrue(true);
    }

    public function test_create_preferences_with_multiple_full_text()
    {
        $connection = $this->getConnection();
        $oraclePreferences = new OraclePreferences($connection);

        $expected = "BEGIN ctx_ddl.create_preference('name_preference', 'MULTI_COLUMN_DATASTORE');
                ctx_ddl.set_attribute('name_preference', 'COLUMNS', '\"FIRSTNAME\", \"LASTNAME\"'); END;";

        $connection->shouldReceive('statement')
            ->once()
            ->andReturnUsing(function ($sql) use ($expected) {
                $this->assertEquals($expected, $sql);
            });

        $blueprint = new Blueprint($connection, 'users');
        $blueprint->fullText(['firstname', 'lastname'], 'name');

        $oraclePreferences->createPreferences($blueprint);
    }

    public function test_create_preferences_with_other_multiple_full_text()
    {
        $connection = $this->getConnection();
        $oraclePreferences = new OraclePreferences($connection);

        $preferences['name_preference'] = "ctx_ddl.create_preference('name_preference', 'MULTI_COLUMN_DATASTORE');
                ctx_ddl.set_attribute('name_preference', 'COLUMNS', '\"FIRSTNAME\", \"LASTNAME\"');";
        $preferences['product_preference'] = "ctx_ddl.create_preference('product_preference', 'MULTI_COLUMN_DATASTORE');
                ctx_ddl.set_attribute('product_preference', 'COLUMNS', '\"CATEGORY\", \"PRICE\"');";

        $expected = 'BEGIN '.implode(' ', $preferences).' END;';

        $connection->shouldReceive('statement')
            ->once()
            ->andReturnUsing(function ($sql) use ($expected) {
                $this->assertEquals($expected, $sql);
            });

        $blueprint = new Blueprint($connection, 'users_product');
        $blueprint->fullText(['firstname', 'lastname'], 'name');
        $blueprint->fullText(['category', 'price'], 'product');

        $oraclePreferences->createPreferences($blueprint);
    }

    public function test_alter_table_creates_full_text_preferences_before_indexes(): void
    {
        $connection = $this->getConnection();
        $statements = [];
        $connection->shouldReceive('statement')->andReturnUsing(function ($sql) use (&$statements) {
            $statements[] = $sql;

            return true;
        });
        $builder = new OracleBuilder($connection);

        $builder->table('users', function (Blueprint $table) {
            $table->fullText(['firstname', 'lastname'], 'name');
        });

        $this->assertCount(2, $statements);
        $this->assertSame("BEGIN ctx_ddl.create_preference('name_preference', 'MULTI_COLUMN_DATASTORE');
                ctx_ddl.set_attribute('name_preference', 'COLUMNS', '\"FIRSTNAME\", \"LASTNAME\"'); END;", $statements[0]);
        $this->assertStringContainsString('create index "NAME_0" on "USERS" ("FIRSTNAME")', $statements[1]);
        $this->assertStringContainsString('create index "NAME_1" on "USERS" ("LASTNAME")', $statements[1]);
        $this->assertStringContainsString('datastore name_preference sync(on commit)', $statements[1]);

        $statements = [];
        $builder->table('users', function (Blueprint $table) {
            $table->string('email');
        });

        $this->assertSame(['alter table "USERS" add ( "EMAIL" varchar2(255) not null )'], $statements);
    }

    public function test_alter_table_with_single_full_text_column_does_not_create_preferences(): void
    {
        $connection = $this->getConnection();
        $statements = [];
        $connection->shouldReceive('statement')->andReturnUsing(function ($sql) use (&$statements) {
            $statements[] = $sql;

            return true;
        });
        $builder = new OracleBuilder($connection);

        $builder->table('users', function (Blueprint $table) {
            $table->fullText('name', 'name_search');
        });

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('create index "NAME_SEARCH" on "USERS" ("NAME")', $statements[0]);
        $this->assertStringNotContainsString('ctx_ddl', $statements[0]);
        $this->assertStringNotContainsString('datastore', $statements[0]);
    }

    public function test_drop_all_preferences_by_table()
    {
        $connection = $this->getConnection();
        $oraclePreferences = new OraclePreferences($connection);

        $expected = "BEGIN
                FOR c IN (select distinct (substr(cui.idx_name, 1, instr(cui.idx_name, '_', -1, 1) - 1) || '_preference') preference
                        from
                            ctxsys.ctx_user_indexes cui
                        where
                            cui.idx_table = ?) LOOP
                    EXECUTE IMMEDIATE 'BEGIN ctx_ddl.drop_preference(:preference); END;'
                    USING c.preference;
                END LOOP;
            END;";

        $connection->shouldReceive('statement')
            ->once()
            ->andReturnUsing(function ($sql, $bindings) use ($expected) {
                $this->assertSame($expected, $sql);
                $this->assertSame(['USERS'], $bindings);
            });

        $oraclePreferences->dropPreferencesByTable('users');
    }

    public function test_drop_all_preferences()
    {
        $connection = $this->getConnection();
        $oraclePreferences = new OraclePreferences($connection);

        $expected = "BEGIN
                FOR c IN (SELECT pre_name FROM ctx_user_preferences) LOOP
                    EXECUTE IMMEDIATE 'BEGIN ctx_ddl.drop_preference(:pre_name); END;'
                    USING c.pre_name;
                END LOOP;
            END;";

        $connection->shouldReceive('statement')
            ->once()
            ->andReturnUsing(function ($sql) use ($expected) {
                $this->assertEquals($expected, $sql);
            });

        $oraclePreferences->dropAllPreferences();
    }

    protected function getConnection(
        ?OracleGrammar $grammar = null,
        ?OracleBuilder $builder = null,
        string $prefix = '',
        int $maxLength = 30,
        string $schemaPrefix = ''
    ) {
        $serverVersion = getenv('SERVER_VERSION') ? getenv('SERVER_VERSION') : '11g';

        $connection = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getConfig')->with('server_version')->andReturn($serverVersion)
            ->shouldReceive('getTablePrefix')->andReturn($prefix)
            ->shouldReceive('getMaxLength')->andReturn($maxLength)
            ->shouldReceive('getSchemaPrefix')->andReturn($schemaPrefix)
            ->shouldReceive('isVersionAboveOrEqual')->andReturnUsing(fn ($version) => version_compare($version, $serverVersion, '<='))
            ->shouldReceive('isMaria')->andReturn(false)
            ->getMock();

        $grammar ??= $this->getGrammar($connection);
        $builder ??= $this->getBuilder();

        return $connection
            ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
            ->shouldReceive('getSchemaBuilder')->andReturn($builder)
            ->getMock();
    }

    public function getGrammar(?Connection $connection = null): OracleGrammar
    {
        return new OracleGrammar($connection ?? $this->getConnection());
    }

    public function getBuilder()
    {
        return mock(OracleBuilder::class);
    }
}
