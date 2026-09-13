<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use LogicException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection as Connection;
use Yajra\Oci8\Schema\Grammars\OracleGrammar;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Schema\OracleBuilder;
use Yajra\Oci8\Schema\OraclePreferences;

class Oci8SchemaGrammarTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_schema_changes_do_not_support_transactions(): void
    {
        $this->assertFalse($this->getGrammar()->supportsSchemaTransactions());
    }

    public function test_basic_create_table()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id');
        $blueprint->string('email');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_create_temporary_table()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->temporary();
        $blueprint->increments('id');
        $blueprint->string('email');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create global temporary table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) ) on commit preserve rows',
            $statements[0]
        );
    }

    public function test_create_table_with_comments()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email')->comment("User's email");
        $blueprint->comment('Application users');

        $statements = $blueprint->toSql();

        $this->assertCount(3, $statements);
        $this->assertEquals(
            'create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
        $this->assertEquals("comment on table \"USERS\" is 'Application users'", $statements[1]);
        $this->assertEquals("comment on column \"USERS\".\"EMAIL\" is 'User''s email'", $statements[2]);
    }

    public function test_add_column_with_comment()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('nickname')->comment('Public nickname');

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals('alter table "USERS" add ( "NICKNAME" varchar2(255) not null )', $statements[0]);
        $this->assertEquals("comment on column \"USERS\".\"NICKNAME\" is 'Public nickname'", $statements[1]);
    }

    public function test_change_column_removes_explicitly_null_comment()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->string('email')->comment(null)->change();

        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) not null',
            'comment on column "USERS"."EMAIL" is \'\'',
        ], $blueprint->toSql());
    }

    public function test_create_table_with_collated_column(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->create();
        $blueprint->string('email')->collation('binary_ci');

        $statements = $blueprint->toSql();

        $this->assertSame([
            'create table "USERS" ( "EMAIL" varchar2(255) collate "BINARY_CI" not null )',
        ], $statements);
    }

    public function test_add_collated_column(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->string('email')->collation('binary_ci');

        $statements = $blueprint->toSql();

        $this->assertSame([
            'alter table "USERS" add ( "EMAIL" varchar2(255) collate "BINARY_CI" not null )',
        ], $statements);
    }

    public function test_create_table_with_invisible_column(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '12c'), 'users');
        $blueprint->create();
        $blueprint->string('email');
        $blueprint->string('internal_note')->invisible();

        $this->assertSame([
            'create table "USERS" ( "EMAIL" varchar2(255) not null, "INTERNAL_NOTE" varchar2(255) invisible not null )',
        ], $blueprint->toSql());
    }

    public function test_add_invisible_column(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '12c'), 'users');
        $blueprint->string('internal_note')->invisible();

        $this->assertSame([
            'alter table "USERS" add ( "INTERNAL_NOTE" varchar2(255) invisible not null )',
        ], $blueprint->toSql());
    }

    public function test_invisible_false_does_not_hide_a_new_column(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '12c'), 'users');
        $blueprint->string('internal_note')->invisible(false);

        $this->assertSame([
            'alter table "USERS" add ( "INTERNAL_NOTE" varchar2(255) not null )',
        ], $blueprint->toSql());
    }

    public function test_change_column_to_invisible_uses_separate_visibility_statement(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '12c'), 'users');
        $blueprint->string('internal_note')->invisible()->change();

        $this->assertSame([
            'alter table "USERS" modify "INTERNAL_NOTE" varchar2(255) not null',
            'alter table "USERS" modify "INTERNAL_NOTE" invisible',
        ], $blueprint->toSql());
    }

    public function test_change_column_to_visible_uses_separate_visibility_statement(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '12c'), 'users');
        $blueprint->string('internal_note')->invisible(false)->change();

        $this->assertSame([
            'alter table "USERS" modify "INTERNAL_NOTE" varchar2(255) not null',
            'alter table "USERS" modify "INTERNAL_NOTE" visible',
        ], $blueprint->toSql());
    }

    public function test_invisible_columns_require_oracle_12c(): void
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '11g'), 'users');
        $blueprint->string('internal_note')->invisible();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invisible columns require Oracle 12c or newer.');

        $blueprint->toSql();
    }

    protected function getConnection(
        ?OracleGrammar $grammar = null,
        ?OracleBuilder $builder = null,
        string $prefix = '',
        int $maxLength = 30,
        string $schemaPrefix = '',
        ?string $serverVersion = null
    ) {
        $serverVersion ??= getenv('SERVER_VERSION') ? getenv('SERVER_VERSION') : '11g';

        $connection = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getConfig')->with('server_version')->andReturn($serverVersion)
            ->shouldReceive('getTablePrefix')->andReturn($prefix)
            ->shouldReceive('getMaxLength')->andReturn($maxLength)
            ->shouldReceive('getSchemaPrefix')->andReturn($schemaPrefix)
            ->shouldReceive('getSchema')->andReturn($schemaPrefix ?: 'TEST_SCHEMA')
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

    public function test_set_schema_prefix_delegates_to_connection(): void
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('setSchemaPrefix')->once()->with('reporting')->andReturnSelf();

        $grammar = new OracleGrammar($conn);

        $grammar->setSchemaPrefix('reporting');

        $this->addToAssertionCount(1);
    }

    public function test_set_max_length_delegates_to_connection(): void
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('setMaxLength')->once()->with(128)->andReturnSelf();

        $grammar = new OracleGrammar($conn);

        $grammar->setMaxLength(128);

        $this->addToAssertionCount(1);
    }

    public function test_get_current_schema_listing_uses_connection_schema(): void
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('getSchemaGrammar')->once()->andReturn(m::mock(OracleGrammar::class));
        $conn->shouldReceive('getSchema')->once()->andReturn('REPORTING');

        $builder = new OracleBuilder($conn);

        $this->assertSame(['REPORTING'], $builder->getCurrentSchemaListing());
    }

    public function test_schema_builder_uses_connection_schema_as_default(): void
    {
        $grammar = m::mock(OracleGrammar::class);
        $processor = m::mock();
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('getSchema')->twice()->andReturn('REPORTING');
        $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
        $grammar->shouldReceive('compileColumnExists')
            ->once()
            ->with('REPORTING', 'prefix_users')
            ->andReturn('column listing sql');
        $connection->shouldReceive('select')->once()->with('column listing sql')->andReturn([]);
        $processor->shouldReceive('processColumnListing')->once()->with([])->andReturn(['id']);
        $connection->shouldReceive('getPostProcessor')->once()->andReturn($processor);

        $builder = new OracleBuilder($connection);

        $this->assertSame(['REPORTING', 'users'], $builder->parseSchemaAndTable('users'));
        $this->assertSame(['id'], $builder->getColumnListing('users'));
    }

    public function test_schema_grammar_uses_connection_schema_as_default(): void
    {
        $grammar = $this->getGrammar($this->getConnection(schemaPrefix: 'reporting'));

        $this->assertStringContainsString("upper(t.owner) = upper('reporting')", $grammar->compileColumns(null, 'users'));
        $this->assertStringContainsString("upper(owner) = upper('reporting')", $grammar->compileViews(null));
        $this->assertStringContainsString("upper(owner) = upper('reporting')", $grammar->compileTypes(null));
        $this->assertStringContainsString("fk.owner = upper('reporting')", $grammar->compileForeignKeys(null, 'users'));
    }

    public function test_schema_builder_uses_connection_schema_for_management_commands(): void
    {
        $grammar = m::mock(OracleGrammar::class);
        $preferences = m::mock(OraclePreferences::class);
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('getSchema')->times(6)->andReturn('REPORTING');
        $preferences->shouldReceive('dropAllPreferences')->once();
        $grammar->shouldReceive('compileDropAllTables')->once()->with('REPORTING')->andReturn('drop tables');
        $grammar->shouldReceive('compileDropAllViews')->once()->with('REPORTING')->andReturn('drop views');
        $grammar->shouldReceive('compileDropAllTypes')->once()->with('REPORTING')->andReturn('drop types');
        $grammar->shouldReceive('compileDisableForeignKeyConstraints')->once()->with('REPORTING')->andReturn('disable foreign keys');
        $grammar->shouldReceive('compileEnableForeignKeyConstraints')->once()->with('REPORTING')->andReturn('enable foreign keys');
        $grammar->shouldReceive('compileTables')->once()->with('REPORTING')->andReturn('list tables');
        $connection->shouldReceive('statement')->once()->with('drop tables')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('drop views')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('drop types')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('disable foreign keys')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('enable foreign keys')->andReturnTrue();
        $connection->shouldReceive('selectFromWriteConnection')->once()->with('list tables')->andReturn([]);
        $processor = m::mock();
        $processor->shouldReceive('processTables')->once()->with([])->andReturn([]);
        $connection->shouldReceive('getPostProcessor')->once()->andReturn($processor);

        $builder = new OracleBuilder($connection);
        $builder->ctxDdlPreferences = $preferences;

        $builder->dropAllTables();
        $builder->dropAllViews();
        $builder->dropAllTypes();
        $this->assertTrue($builder->disableForeignKeyConstraints());
        $this->assertTrue($builder->enableForeignKeyConstraints());
        $this->assertSame([], $builder->getTables());
    }

    public function test_create_database_is_not_supported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oracle does not support creating databases via the schema builder.');

        $this->getGrammar()->compileCreateDatabase('testing');
    }

    public function test_drop_database_if_exists_is_not_supported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oracle does not support dropping databases via the schema builder.');

        $this->getGrammar()->compileDropDatabaseIfExists('testing');
    }

    public function test_add_column_with_space(): void
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->string('first name');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "FIRST NAME" varchar2(255) not null )', $statements[0]);
    }

    public function test_set_schema_prefix()
    {
        $conn = $this->getConnection(schemaPrefix: 'schema');

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->string('first name');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "SCHEMA"."USERS" ( "FIRST NAME" varchar2(255) not null )', $statements[0]);
    }

    public function test_create_index_name_using_column_with_space()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->string('first name');
        $blueprint->index('first name');

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals('create table "USERS" ( "FIRST NAME" varchar2(255) not null )', $statements[0]);
        $this->assertEquals('create index "USERS_FIRST_NAME_INDEX" on "USERS" ( "FIRST NAME" )', $statements[1]);
    }

    public function test_basic_create_table_with_reserved_words()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('group');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "GROUP" varchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]);
    }

    public function test_create_table_wraps_reserved_constraint_names(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->create();
        $blueprint->integer('id');
        $blueprint->integer('role_id');
        $blueprint->primary('id', 'primary');
        $blueprint->foreign('role_id', 'foreign')->references('id')->on('roles');

        $this->assertSame([
            'create table "USERS" ( "ID" number(10,0) not null, "ROLE_ID" number(10,0) not null, constraint "FOREIGN" foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" ), constraint "PRIMARY" primary key ( "ID" ) )',
        ], $blueprint->toSql());
    }

    public function test_basic_create_table_with_primary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]);
    }

    public function test_basic_create_table_with_primary_and_foreign_keys()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint "PREFIX_USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_create_table_with_nvarchar2()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->nvarchar2('first_name');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "USERS" ( "ID" number(10,0) not null, "FIRST_NAME" nvarchar2(255) not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_create_table_with_default_value_and_is_not_null()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email')->default('user@test.com');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) default \'user@test.com\' not null, constraint "USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]);
    }

    public function test_basic_create_table_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $conn->shouldReceive('getConfig')->andReturn(null);

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]);
    }

    public function test_basic_create_table_with_prefix_and_primary()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $grammar = $this->getGrammar();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]);
    }

    public function test_basic_create_table_with_prefix_primary_and_foreign_keys()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $grammar = $this->getGrammar();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint "PREFIX_USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_create_table_with_prefix_primary_and_foreign_keys_with_cascade_delete()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint "PREFIX_USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ) on delete cascade, constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_create_table_omits_default_foreign_key_delete_actions()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->integer('role_id');
        $blueprint->integer('team_id');
        $blueprint->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        $blueprint->foreign('team_id')->references('id')->on('teams')->noActionOnDelete();
        $blueprint->create();

        $this->assertSame([
            'create table "USERS" ( "ROLE_ID" number(10,0) not null, "TEAM_ID" number(10,0) not null, constraint "USERS_ROLE_ID_FK" foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" ), constraint "USERS_TEAM_ID_FK" foreign key ( "TEAM_ID" ) references "TEAMS" ( "ID" ) )',
        ], $blueprint->toSql());
    }

    public function test_create_table_rejects_foreign_key_update_actions()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->integer('role_id');
        $blueprint->foreign('role_id')->references('id')->on('roles')->cascadeOnUpdate();
        $blueprint->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Oracle does not support ON UPDATE actions [cascade] on foreign keys.');

        $blueprint->toSql();
    }

    public function test_basic_create_table_with_deferrable_foreign_key()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->deferrable()->initiallyImmediate(false);
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint "PREFIX_USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ) deferrable initially deferred, constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_create_table_with_not_valid_foreign_key()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->notValid();
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint "PREFIX_USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ) enable novalidate, constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function test_basic_alter_table()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "ID" number(10,0) not null )',
            'alter table "USERS" add ( "EMAIL" varchar2(255) not null )',
        ], $statements);
    }

    public function test_alter_table_rename_column()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->renameColumn('email', 'email_address');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" rename column "EMAIL" to "EMAIL_ADDRESS"', $statements[0]);
    }

    public function test_alter_table_rename_multiple_columns()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->renameColumn('email', 'email_address');
        $blueprint->renameColumn('address', 'address_1');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals('alter table "USERS" rename column "EMAIL" to "EMAIL_ADDRESS"', $statements[0]);
        $this->assertEquals('alter table "USERS" rename column "ADDRESS" to "ADDRESS_1"', $statements[1]);
    }

    public function test_alter_table_modify_column()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->change();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" modify "EMAIL" varchar2(255) not null', $statements[0]);
    }

    public function test_alter_table_modify_column_with_collate()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->change()->collation('latin1_swedish_ci');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) collate "LATIN1_SWEDISH_CI" not null',
        ], $statements);
    }

    public function test_alter_table_modify_multiple_columns()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->change();
        $blueprint->string('name')->change();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) not null',
            'alter table "USERS" modify "NAME" varchar2(255) not null',
        ], $statements);
    }

    public function test_alter_table_modify_column_preserves_nullable_when_already_nullable()
    {
        // Test case from issue #941: modifying nullable column with ->nullable() should not fail
        $conn = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getSchema')->andReturn('TEST_SCHEMA')
            ->shouldReceive('getTablePrefix')->andReturn('')
            ->shouldReceive('getMaxLength')->andReturn(30)
            ->shouldReceive('getSchemaPrefix')->andReturn('')
            ->shouldReceive('isMaria')->andReturn(false)
            ->shouldReceive('selectOne')
            ->andReturn((object) ['nullable' => 1]) // Column is currently nullable
            ->getMock();

        // Create grammar with the connection so it can access it
        $grammar = new OracleGrammar($conn);
        $conn->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($this->getBuilder());

        $blueprint = new Blueprint($conn, 'attributes');
        $blueprint->string('validation_regex', 1024)->nullable()->change();

        // The grammar needs to be set on the blueprint's connection
        // Blueprint gets grammar from connection->getSchemaGrammar()
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        // Should NOT include 'null' since column is already nullable (prevents ORA-01451)
        $this->assertStringNotContainsString(' null', $statements[0]);
        $this->assertStringNotContainsString('not null', $statements[0]);
        $this->assertStringContainsString('varchar2(1024)', $statements[0]);
    }

    public function test_alter_table_modify_column_changes_nullable_to_not_null_when_not_specified()
    {
        $conn = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getSchema')->andReturn('TEST_SCHEMA')
            ->shouldReceive('getTablePrefix')->andReturn('')
            ->shouldReceive('getMaxLength')->andReturn(30)
            ->shouldReceive('getSchemaPrefix')->andReturn('')
            ->shouldReceive('isMaria')->andReturn(false)
            ->shouldReceive('selectOne')
            ->andReturn((object) ['nullable' => 1]) // Column is currently nullable
            ->getMock();

        // Create grammar with the connection so it can access it
        $grammar = new OracleGrammar($conn);
        $conn->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($this->getBuilder());

        $blueprint = new Blueprint($conn, 'attributes');
        $blueprint->string('validation_regex', 1024)->change();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(
            'alter table "ATTRIBUTES" modify "VALIDATION_REGEX" varchar2(1024) not null',
            $statements[0]
        );
    }

    public function test_alter_table_modify_column_changes_nullable_to_not_null()
    {
        $conn = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getSchema')->andReturn('TEST_SCHEMA')
            ->shouldReceive('getTablePrefix')->andReturn('')
            ->shouldReceive('getMaxLength')->andReturn(30)
            ->shouldReceive('getSchemaPrefix')->andReturn('')
            ->shouldReceive('isMaria')->andReturn(false)
            ->shouldReceive('selectOne')
            ->andReturn((object) ['nullable' => 1]) // Column is currently nullable
            ->getMock();

        // Create grammar with the connection so it can access it
        $grammar = new OracleGrammar($conn);
        $conn->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($this->getBuilder());

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->nullable(false)->change();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" modify "EMAIL" varchar2(255) not null', $statements[0]);
    }

    public function test_alter_table_modify_column_changes_not_null_to_nullable()
    {
        // Test changing from not null to nullable
        $conn = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getSchema')->andReturn('TEST_SCHEMA')
            ->shouldReceive('getTablePrefix')->andReturn('')
            ->shouldReceive('getMaxLength')->andReturn(30)
            ->shouldReceive('getSchemaPrefix')->andReturn('')
            ->shouldReceive('isMaria')->andReturn(false)
            ->shouldReceive('selectOne')
            ->andReturn((object) ['nullable' => 0]) // Column is currently not null
            ->getMock();

        // Create grammar with the connection so it can access it
        $grammar = new OracleGrammar($conn);
        $conn->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($this->getBuilder());

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->nullable()->change(); // Changing to nullable

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        // Should include ' null' since we're changing from not null to nullable
        $this->assertStringContainsString(' null', $statements[0]);
        $this->assertStringNotContainsString('not null', $statements[0]);
    }

    public function test_alter_table_modify_column_removes_explicitly_null_default()
    {
        $conn = m::mock(Connection::class)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('getSchema')->andReturn('TEST_SCHEMA')
            ->shouldReceive('getTablePrefix')->andReturn('')
            ->shouldReceive('getMaxLength')->andReturn(30)
            ->shouldReceive('getSchemaPrefix')->andReturn('')
            ->shouldReceive('isMaria')->andReturn(false)
            ->shouldReceive('selectOne')
            ->andReturn((object) ['nullable' => 1])
            ->getMock();

        $grammar = new OracleGrammar($conn);
        $conn->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($this->getBuilder());

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('email')->nullable()->default(null)->change();

        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) default null',
        ], $blueprint->toSql());
    }

    public function test_basic_alter_table_with_primary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id')->primary();
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(3, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "ID" number(10,0) not null )',
            'alter table "USERS" add ( "EMAIL" varchar2(255) not null )',
            'alter table "USERS" add constraint "USERS_ID_PK" primary key ("ID")',
        ], $statements);
    }

    public function test_basic_alter_table_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "PREFIX_USERS" add ( "ID" number(10,0) not null )',
            'alter table "PREFIX_USERS" add ( "EMAIL" varchar2(255) not null )',
        ], $statements);
    }

    public function test_basic_alter_table_with_prefix_and_primary()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id')->primary();
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(3, $statements);
        $this->assertEquals([
            'alter table "PREFIX_USERS" add ( "ID" number(10,0) not null )',
            'alter table "PREFIX_USERS" add ( "EMAIL" varchar2(255) not null )',
            'alter table "PREFIX_USERS" add constraint "PREFIX_USERS_ID_PK" primary key ("ID")',
        ], $statements);
    }

    public function test_drop_table()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->drop();
        $statements = $blueprint->toSql();

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop table "USERS"', $statements[0]);
    }

    public function test_compile_schemas_method()
    {
        $grammar = $this->getGrammar();
        $expected = 'select lower(username) as "name", decode(username, user, 1, 0) as "default" from all_users order by username';
        $sql = $grammar->compileSchemas();
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_table_exists_method()
    {
        $grammar = $this->getGrammar();
        $expected = "select count(*) from all_tables where upper(owner) = upper('schema') and upper(table_name) = upper('test_table')";
        $sql = $grammar->compileTableExists('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_column_exists_method()
    {
        $grammar = $this->getGrammar();
        $expected = 'select column_name from all_tab_cols where upper(owner) = upper(\'schema\') and upper(table_name) = upper(\'test_table\') order by column_id';
        $sql = $grammar->compileColumnExists('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_columns_method()
    {
        $grammar = $this->getGrammar();
        $conn = $this->getConnection();
        $expected = '
            select
                t.column_name as name,
                nvl(t.data_type_mod, data_type) as type_name,
                '.($conn->isVersionAboveOrEqual('12c') ? "decode(t.identity_column, 'YES', 1, 0) as auto_increment," : 'null as auto_increment,').'
                t.data_type as type,
                t.data_length,
                t.char_length,
                t.data_precision as precision,
                t.data_scale as places,
                '.($conn->isVersionAboveOrEqual('12cR2') ? 'lower(t.collation) as collation,' : 'null as collation,').'
                decode(t.virtual_column, \'YES\', \'virtual\', null) as generated,
                decode(t.nullable, \'Y\', 1, 0) as nullable,
                t.data_default as "default",
                c.comments as "comment"
            from all_tab_cols t
            left join all_col_comments c on t.owner = c.owner and t.table_name = c.table_name AND t.column_name = c.column_name
            where upper(t.table_name) = upper(\'test_table\')
                and upper(t.owner) = upper(\'schema\')
                '.($conn->isVersionAboveOrEqual('12c') ? "and (t.hidden_column = 'NO' or t.user_generated = 'YES')" : "and t.hidden_column = 'NO'").'
            order by
                t.column_id
        ';

        $sql = $grammar->compileColumns('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_views_method()
    {
        $grammar = $this->getGrammar();

        $expected = "select lower(view_name) as name, lower(owner) as schema, text as definition from all_views where upper(owner) = upper('schema') order by owner, view_name";

        $sql = $grammar->compileViews('schema');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_types_method()
    {
        $grammar = $this->getGrammar();

        $expected = "select lower(type_name) as name, lower(owner) as schema, lower(typecode) as type, lower(typecode) as category, 0 as implicit from all_types where predefined = 'NO' and typecode != 'COLLECTION' and upper(owner) = upper('schema') union all select lower(type_name) as name, lower(owner) as schema, lower(replace(coll_type, ' ', '_')) as type, 'collection' as category, 0 as implicit from all_coll_types where upper(owner) = upper('schema') order by schema, name";

        $sql = $grammar->compileTypes('schema');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_indexes_method()
    {
        $grammar = $this->getGrammar();

        $expected = 'select i.index_name as name, LISTAGG(coalesce(extractvalue(dbms_xmlgen.getxmltype(\'select column_expression from all_ind_expressions where index_owner = \'\'\' || i.index_owner || \'\'\' and index_name = \'\'\' || i.index_name || \'\'\' and table_owner = \'\'\' || i.table_owner || \'\'\' and table_name = \'\'\' || i.table_name || \'\'\' and column_position = \' || i.column_position), \'/ROWSET/ROW/COLUMN_EXPRESSION\'), i.column_name), \',\') WITHIN GROUP (ORDER BY i.column_position) as columns, a.index_type as type, decode(a.uniqueness, \'UNIQUE\', 1, 0) as "unique", max(decode(c.constraint_type, \'P\', 1, 0)) as "primary" from all_ind_columns i join all_indexes a on a.owner = i.index_owner and a.index_name = i.index_name and a.table_owner = i.table_owner and a.table_name = i.table_name left join all_constraints c on c.owner = a.table_owner and c.index_name = a.index_name and c.constraint_type = \'P\' where i.table_owner = upper(\'schema\') and i.table_name = upper(\'test_table\') group by i.index_name, a.index_type, a.uniqueness order by i.index_name';

        $sql = $grammar->compileIndexes('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function test_compile_foreign_keys_method()
    {
        $grammar = $this->getGrammar();
        $expected = '
            select
                fk.constraint_name as name,
                LISTAGG(fkc.column_name, \',\') WITHIN GROUP (ORDER BY fkc.position) as columns,
                fk.r_owner as foreign_schema,
                pkc.table_name as foreign_table,
                LISTAGG(pkc.column_name, \',\') WITHIN GROUP (ORDER BY fkc.position) as foreign_columns,
                fk.delete_rule AS "on_delete",
                null AS "on_update"
            from all_constraints fk
            inner join all_cons_columns fkc
                on fkc.owner = fk.owner
                and fkc.constraint_name = fk.constraint_name
                and fkc.table_name = fk.table_name
            inner join all_cons_columns pkc
                on pkc.owner = fk.r_owner
                and pkc.constraint_name = fk.r_constraint_name
                and pkc.position = fkc.position
            where fk.owner = upper(\'schema\')
                and fk.table_name = upper(\'test_table\')
                and fk.constraint_type = \'R\'
            group by
                fk.constraint_name, fk.r_owner, pkc.table_name, fk.delete_rule
        ';

        $sql = $grammar->compileForeignKeys('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function test_drop_table_if_exists_on_pre_23ai_suppresses_missing_or_invalid_table_names()
    {
        $conn = $this->getConnection(serverVersion: '21c');
        $conn->shouldReceive('getConfig')->with('username')->andReturn('system');

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropIfExists();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals("declare
            table_does_not_exist exception;
            identifier_is_too_long exception;
            pragma exception_init(table_does_not_exist, -942);
            pragma exception_init(identifier_is_too_long, -972);
        begin
            execute immediate 'drop table \"USERS\"';
        exception
            when table_does_not_exist or identifier_is_too_long then null;
        end;", $statements[0]);
    }

    public function test_drop_table_if_exists_on_pre_23ai_escapes_dynamic_sql_literals()
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '21c'), "owner's table");
        $blueprint->dropIfExists();

        $this->assertSame([
            "declare
            table_does_not_exist exception;
            identifier_is_too_long exception;
            pragma exception_init(table_does_not_exist, -942);
            pragma exception_init(identifier_is_too_long, -972);
        begin
            execute immediate 'drop table \"OWNER''S TABLE\"';
        exception
            when table_does_not_exist or identifier_is_too_long then null;
        end;",
        ], $blueprint->toSql());
    }

    public function test_drop_table_if_exists_uses_native_syntax_on_23ai()
    {
        $blueprint = new Blueprint($this->getConnection(serverVersion: '23ai'), 'users');
        $blueprint->dropIfExists();

        $this->assertSame([
            'drop table if exists "USERS"',
        ], $blueprint->toSql());
    }

    public function test_drop_table_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->drop();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('drop table "PREFIX_USERS"', $statements[0]);
    }

    public function test_drop_column()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropColumn('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "FOO" )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropColumn(['foo', 'bar']);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "FOO", "BAR" )', $statements[0]);
    }

    public function test_drop_primary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropPrimary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint "FOO"', $statements[0]);
    }

    public function test_drop_unique()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropUnique('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint "FOO"', $statements[0]);
    }

    public function test_drop_index()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropIndex('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('drop index "FOO"', $statements[0]);
    }

    public function test_drop_foreign()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropForeign('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint "FOO"', $statements[0]);
    }

    public function test_drop_commands_wrap_reserved_constraint_and_index_names(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dropPrimary('primary');
        $blueprint->dropUnique('unique');
        $blueprint->dropForeign('foreign');
        $blueprint->dropIndex('index');

        $this->assertSame([
            'alter table "USERS" drop constraint "PRIMARY"',
            'alter table "USERS" drop constraint "UNIQUE"',
            'alter table "USERS" drop constraint "FOREIGN"',
            'drop index "INDEX"',
        ], $blueprint->toSql());
    }

    public function test_drop_commands_preserve_long_explicit_names(): void
    {
        $name = 'users_external_identity_provider_reference_unique';
        $blueprint = new Blueprint($this->getConnection(maxLength: 30), 'users');
        $blueprint->dropPrimary($name);
        $blueprint->dropUnique($name);
        $blueprint->dropForeign($name);
        $blueprint->dropIndex($name);

        $this->assertSame([
            'alter table "USERS" drop constraint "USERS_EXTERNAL_IDENTITY_PROVIDER_REFERENCE_UNIQUE"',
            'alter table "USERS" drop constraint "USERS_EXTERNAL_IDENTITY_PROVIDER_REFERENCE_UNIQUE"',
            'alter table "USERS" drop constraint "USERS_EXTERNAL_IDENTITY_PROVIDER_REFERENCE_UNIQUE"',
            'drop index "USERS_EXTERNAL_IDENTITY_PROVIDER_REFERENCE_UNIQUE"',
        ], $blueprint->toSql());
    }

    public function test_generated_index_name_uses_a_deterministic_fallback_when_parts_cannot_be_shortened_further(): void
    {
        $connection = $this->getConnection(maxLength: 30);
        $table = 'users_with_a_very_long_name';
        $column = 'external_identity_provider_reference';

        $first = (new Blueprint($connection, $table))->unique($column)->index;
        $second = (new Blueprint($connection, $table))->unique($column)->index;

        $this->assertSame($first, $second);
        $this->assertLessThanOrEqual(30, strlen($first));
        $this->assertMatchesRegularExpression('/_[a-f0-9]{8}$/', $first);
    }

    public function test_generated_index_name_keeps_the_existing_shortening_when_it_can_fit(): void
    {
        $blueprint = new Blueprint($this->getConnection(maxLength: 30), 'verylongusers');

        $index = $blueprint->unique('verylongreference')->index;

        $this->assertSame('verylonguse_verylongreferen_uk', $index);
        $this->assertSame(30, strlen($index));
    }

    public function test_generated_index_name_shortening_handles_multibyte_parts_by_byte_length(): void
    {
        $blueprint = new Blueprint($this->getConnection(maxLength: 30), '使用者_資料');

        $index = $blueprint->unique('外部_識別碼')->index;

        $this->assertSame('使用_資料_外部_識別_uk', $index);
        $this->assertSame(30, strlen($index));
    }

    public function test_drop_timestamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dropTimestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "CREATED_AT", "UPDATED_AT" )', $statements[0]);
    }

    public function test_single_drop_full_text_by_index()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dropFullText('name_index');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('drop index "NAME_INDEX"', $statements[0]);
    }

    public function test_multiple_drop_full_text_by_columns_is_scoped_to_the_table()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dropFullText(['firstname', 'lastname']);
        $statements = $blueprint->toSql();

        $expected = "begin for idx_rec in (select idx_name from ctx_user_indexes where idx_table = 'USERS' and idx_text_name in ('FIRSTNAME', 'LASTNAME')) loop
            execute immediate 'drop index ' || idx_rec.idx_name;
        end loop; end;";

        $this->assertCount(1, $statements);
        $this->assertEquals($expected, $statements[0]);
    }

    public function test_drop_full_text_by_columns_uses_the_physical_table_name()
    {
        $blueprint = new Blueprint($this->getConnection(prefix: 'prefix_'), 'reporting.users');
        $blueprint->dropFullText(['name']);

        $this->assertSame([
            "begin for idx_rec in (select idx_name from ctx_user_indexes where idx_table = 'PREFIX_USERS' and idx_text_name in ('NAME')) loop
            execute immediate 'drop index ' || idx_rec.idx_name;
        end loop; end;",
        ], $blueprint->toSql());
    }

    public function test_drop_spatial_index()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dropSpatialIndex('users_location_spatialindex');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame('drop index "USERS_LOCATION_SPATIALINDEX"', $statements[0]);
    }

    public function test_rename_table()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->rename('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" rename to "FOO"', $statements[0]);
    }

    public function test_rename_table_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->rename('foo');
        $grammar = $this->getGrammar();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" rename to "PREFIX_FOO"', $statements[0]);
    }

    public function test_rename_index()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->renameIndex('users_email_index', 'users_email_address_index');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter index "USERS_EMAIL_INDEX" rename to "USERS_EMAIL_ADDRESS_INDEX"',
            $statements[0]
        );
    }

    public function test_rename_index_wraps_reserved_names(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->renameIndex('index', 'select');

        $this->assertSame([
            'alter index "INDEX" rename to "SELECT"',
        ], $blueprint->toSql());
    }

    public function test_add_commands_wrap_reserved_constraint_and_index_names(): void
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->primary('id', 'primary');
        $blueprint->unique('email', 'unique');
        $blueprint->foreign('role_id', 'foreign')->references('id')->on('roles');
        $blueprint->index('name', 'index');

        $this->assertSame([
            'alter table "USERS" add constraint "PRIMARY" primary key ("ID")',
            'alter table "USERS" add constraint "UNIQUE" unique ( "EMAIL" )',
            'alter table "USERS" add constraint "FOREIGN" foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" )',
            'create index "INDEX" on "USERS" ( "NAME" )',
        ], $blueprint->toSql());
    }

    public function test_adding_primary_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->primary('foo', 'bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "BAR" primary key ("FOO")', $statements[0]);
    }

    public function test_adding_primary_key_with_constraint_automatic_name()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->primary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "USERS_FOO_PK" primary key ("FOO")', $statements[0]);
    }

    public function test_adding_primary_key_with_constraint_automatic_name_greater_than_thirty_characters()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->primary('reset_password_secret_code');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add constraint "USER_RESE_PASSWOR_SECRE_COD_PK" primary key ("RESET_PASSWORD_SECRET_CODE")',
            $statements[0]);
    }

    public function test_adding_unique_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->unique('foo', 'bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "BAR" unique ( "FOO" )', $statements[0]);
    }

    public function test_adding_deferrable_unique_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->unique('foo', 'bar')->deferrable()->initiallyImmediate(false);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "BAR" unique ( "FOO" ) deferrable initially deferred', $statements[0]);
    }

    public function test_adding_online_unique_key()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->unique('foo', 'bar')->online();

        $this->assertSame([
            'alter table "USERS" add constraint "BAR" unique ( "FOO" ) using index (create unique index "BAR" on "USERS" ( "FOO" ) online)',
        ], $blueprint->toSql());
    }

    public function test_adding_online_deferrable_unique_key_uses_nonunique_index()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->unique('foo', 'bar')->online()->deferrable()->initiallyImmediate(false);

        $this->assertSame([
            'alter table "USERS" add constraint "BAR" unique ( "FOO" ) deferrable initially deferred using index (create index "BAR" on "USERS" ( "FOO" ) online)',
        ], $blueprint->toSql());
    }

    public function test_adding_defined_unique_key_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->unique('foo', 'bar');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" add constraint "BAR" unique ( "FOO" )', $statements[0]);
    }

    public function test_adding_generated_unique_key_with_prefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->unique('foo');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" add constraint "PREFIX_USERS_FOO_UK" unique ( "FOO" )',
            $statements[0]);
    }

    public function test_adding_index()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->index(['foo', 'bar'], 'baz');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create index "BAZ" on "USERS" ( "FOO", "BAR" )', $statements[0]);
    }

    public function test_adding_online_index()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->index(['foo', 'bar'], 'baz')->online();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create index "BAZ" on "USERS" ( "FOO", "BAR" ) online', $statements[0]);
    }

    public function test_adding_m_single_column_full_text_index()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->fullText(['name'], 'name');
        $statements = $blueprint->toSql();

        $expected = "begin execute immediate 'create index \"NAME\" on \"USERS\" (\"NAME\") indextype is ctxsys.context parameters (''sync(on commit)'')'; end;";

        $this->assertCount(1, $statements);
        $this->assertEquals($expected, $statements[0]);
    }

    public function test_adding_multiple_columns_full_text_index()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->fullText(['firstname', 'lastname'], 'name');
        $statements = $blueprint->toSql();

        $expectedSql['firstnameIndex'] = "execute immediate 'create index \"NAME_0\" on \"USERS\" (\"FIRSTNAME\") indextype is ctxsys.context parameters (''datastore name_preference sync(on commit)'')';";
        $expectedSql['lastnameIndex'] = "execute immediate 'create index \"NAME_1\" on \"USERS\" (\"LASTNAME\") indextype is ctxsys.context parameters (''datastore name_preference sync(on commit)'')';";
        $expected = 'begin '.implode(' ', $expectedSql).' end;';

        $this->assertCount(1, $statements);
        $this->assertEquals($expected, $statements[0]);
    }

    public function test_full_text_indexes_wrap_reserved_and_mixed_case_column_names()
    {
        $blueprint = new Blueprint($this->getConnection(), 'articles');
        $blueprint->fullText(['select', 'mixedCase'], 'article_search');

        $this->assertSame([
            "begin execute immediate 'create index \"ARTICLE_SEARCH_0\" on \"ARTICLES\" (\"SELECT\") indextype is ctxsys.context parameters (''datastore article_search_preference sync(on commit)'')'; execute immediate 'create index \"ARTICLE_SEARCH_1\" on \"ARTICLES\" (\"MIXEDCASE\") indextype is ctxsys.context parameters (''datastore article_search_preference sync(on commit)'')'; end;",
        ], $blueprint->toSql());
    }

    public function test_adding_foreign_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" )',
            $statements[0]);
    }

    public function test_adding_deferrable_foreign_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->deferrable()->initiallyImmediate(false);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add constraint "USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" ) deferrable initially deferred',
            $statements[0]
        );
    }

    public function test_adding_not_valid_foreign_key()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->notValid();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add constraint "USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" ) enable novalidate',
            $statements[0]
        );
    }

    public function test_adding_foreign_key_with_cascade_delete()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint "USERS_FOO_ID_FK" foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" ) on delete cascade',
            $statements[0]);
    }

    public function test_adding_foreign_key_with_set_null_delete()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->foreign('role_id')->references('id')->on('roles')->nullOnDelete();

        $this->assertSame([
            'alter table "USERS" add constraint "USERS_ROLE_ID_FK" foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" ) on delete set null',
        ], $blueprint->toSql());
    }

    public function test_adding_foreign_key_rejects_unsupported_delete_actions()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->foreign('role_id')->references('id')->on('roles')->onDelete('set default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Oracle does not support the ON DELETE action [set default].');

        $blueprint->toSql();
    }

    public function test_adding_incrementing_id()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "ID" number(10,0) not null )', $statements[0]);
    }

    public function test_adding_string()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) default \'bar\' null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->string('foo', 100)
            ->nullable()
            ->default(new Expression('CURRENT TIMESTAMP'));
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) default CURRENT TIMESTAMP null )',
            $statements[0]);
    }

    public function test_adding_long_text()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->longText('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function test_adding_medium_text()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->mediumText('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function test_adding_text()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->text('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function test_adding_tiny_text()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->tinyText('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) not null )', $statements[0]);
    }

    public function test_adding_char()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->char('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(255) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->char('foo', 1);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(1) not null )', $statements[0]);
    }

    public function test_adding_big_integer()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(19,0) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "FOO" number(19,0) not null )',
        ], $statements);
    }

    public function test_adding_integer()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(['alter table "USERS" add ( "FOO" number(10,0) not null )'], $statements);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('foo', true)->primary();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "FOO" number(10,0) not null )',
            'alter table "USERS" add constraint "USERS_FOO_PK" primary key ("FOO")',
        ], $statements);
    }

    public function test_adding_medium_integer()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->mediumInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(['alter table "USERS" add ( "FOO" number(7,0) not null )'], $statements);
    }

    public function test_adding_small_integer()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(5,0) not null )', $statements[0]);
    }

    public function test_adding_tiny_integer()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(3,0) not null )', $statements[0]);
    }

    public function test_adding_float()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->float('foo', 5);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" float(5) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->float('foo');
        $statements = $blueprint->toSql();
        $this->assertEquals('alter table "USERS" add ( "FOO" float(126) not null )', $statements[0]);
    }

    public function test_adding_real()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->addColumn('real', 'foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" binary_float not null )', $statements[0]);
    }

    public function test_adding_double()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->double('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" float(126) not null )', $statements[0]);
    }

    public function test_adding_decimal()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(5, 2) not null )', $statements[0]);
    }

    public function test_adding_boolean()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(1) not null )', $statements[0]);
    }

    public function test_adding_enum()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->enum('foo', ['bar', 'baz']);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) not null check ("FOO" in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function test_adding_enum_with_default_value()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->enum('foo', ['bar', 'baz'])->default('bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) default \'bar\' not null check ("FOO" in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function test_adding_enum_escapes_allowed_values()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->enum('role', ["manager's", "'quoted'"]);

        $this->assertSame([
            'alter table "USERS" add ( "ROLE" varchar2(255) not null check ("ROLE" in (\'manager\'\'s\', \'\'\'quoted\'\'\')) )',
        ], $blueprint->toSql());
    }

    public function test_adding_year()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->year('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(10,0) not null )', $statements[0]);
    }

    public function test_adding_year_with_current_default()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->year('foo')->useCurrent();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add ( "FOO" number(10,0) default EXTRACT(YEAR FROM CURRENT_DATE) not null )',
            $statements[0]
        );
    }

    public function test_adding_json()
    {
        $conn = $this->getConnection(serverVersion: '19c');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->json('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add ( "FOO" clob not null check ("FOO" is json) )',
            $statements[0]
        );
    }

    public function test_adding_json_on_oracle_21c_uses_native_type(): void
    {
        $conn = $this->getConnection(serverVersion: '21c');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->json('foo');

        $this->assertSame([
            'alter table "USERS" add ( "FOO" json not null )',
        ], $blueprint->toSql());
    }

    public function test_adding_json_on_oracle_11g_uses_unconstrained_clob(): void
    {
        $conn = $this->getConnection(serverVersion: '11g');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->json('foo');

        $this->assertSame([
            'alter table "USERS" add ( "FOO" clob not null )',
        ], $blueprint->toSql());
    }

    public function test_adding_jsonb()
    {
        $conn = $this->getConnection(serverVersion: '19c');
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->jsonb('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add ( "FOO" clob not null check ("FOO" is json) )',
            $statements[0]
        );
    }

    public function test_adding_date()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->date('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function test_adding_date_with_current_default()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->date('foo')->useCurrent();
        $statements = $blueprint->toSql();

        $this->assertSame([
            'alter table "USERS" add ( "FOO" date default CURRENT_DATE not null )',
        ], $statements);
    }

    public function test_adding_date_time()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->dateTime('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function test_adding_date_time_with_current_default()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dateTime('foo')->useCurrent();
        $statements = $blueprint->toSql();

        $this->assertSame([
            'alter table "USERS" add ( "FOO" date default CURRENT_TIMESTAMP not null )',
        ], $statements);
    }

    public function test_adding_date_time_tz_with_precision()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->dateTimeTz('foo', 3);

        $this->assertSame([
            'alter table "USERS" add ( "FOO" timestamp(3) with time zone not null )',
        ], $blueprint->toSql());
    }

    public function test_adding_time()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->time('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function test_adding_time_stamp()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->timestamp('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" timestamp(0) not null )', $statements[0]);
    }

    public function test_adding_time_stamp_with_precision()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->timestamp('foo', 6);

        $this->assertSame([
            'alter table "USERS" add ( "FOO" timestamp(6) not null )',
        ], $blueprint->toSql());
    }

    public function test_adding_time_stamp_with_current_default()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->timestamp('foo')->useCurrent();
        $statements = $blueprint->toSql();

        $this->assertSame([
            'alter table "USERS" add ( "FOO" timestamp(0) default CURRENT_TIMESTAMP not null )',
        ], $statements);
    }

    public function test_adding_time_stamp_tz()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->timestampTz('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" timestamp(0) with time zone not null )', $statements[0]);
    }

    public function test_adding_time_stamp_tz_with_precision()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->timestampTz('foo', 9);

        $this->assertSame([
            'alter table "USERS" add ( "FOO" timestamp(9) with time zone not null )',
        ], $blueprint->toSql());
    }

    public function test_adding_time_stamp_tz_with_current_default()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->timestampTz('foo')->useCurrent();
        $statements = $blueprint->toSql();

        $this->assertSame([
            'alter table "USERS" add ( "FOO" timestamp(0) with time zone default CURRENT_TIMESTAMP not null )',
        ], $statements);
    }

    public function test_adding_nullable_time_stamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->nullableTimestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "CREATED_AT" timestamp(0) null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp(0) null )',
        ], $statements);
    }

    public function test_adding_time_stamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->timestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "CREATED_AT" timestamp(0) null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp(0) null )',
        ], $statements);
    }

    public function test_adding_time_stamp_tzs()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->timestampsTz();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals([
            'alter table "USERS" add ( "CREATED_AT" timestamp(0) with time zone null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp(0) with time zone null )',
        ], $statements);
    }

    public function test_adding_uuid()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->uuid('foo');
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(36) not null )', $statements[0]);
    }

    public function test_adding_binary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn, 'users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" blob not null )', $statements[0]);
    }

    public function test_basic_create_table_with_primary_and_long_foreign_keys()
    {
        $conn = $this->getConnection(prefix: 'prefix_', maxLength: 120);
        $conn->shouldReceive('setMaxLength');
        $conn->setMaxLength(120);

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('very_long_foo_bar_id');
        $blueprint->foreign('very_long_foo_bar_id')->references('id')->on('orders');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "VERY_LONG_FOO_BAR_ID" number(10,0) not null, constraint "PREFIX_USERS_VERY_LONG_FOO_BAR_ID_FK" foreign key ( "VERY_LONG_FOO_BAR_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint "PREFIX_USERS_ID_PK" primary key ( "ID" ) )',
        ], $statements);
    }

    public function test_drop_all_tables()
    {
        $statement = $this->getGrammar()->compileDropAllTables('reporting');

        $expected = 'BEGIN
            FOR c IN (SELECT owner, table_name FROM all_tables WHERE owner = \'REPORTING\' AND secondary = \'N\') LOOP
            EXECUTE IMMEDIATE (\'DROP TABLE "\' || replace(c.owner, \'"\', \'""\') || \'"."\' || replace(c.table_name, \'"\', \'""\') || \'" CASCADE CONSTRAINTS PURGE\');
            END LOOP;

            FOR s IN (SELECT sequence_owner, sequence_name FROM all_sequences WHERE sequence_owner = \'REPORTING\' AND sequence_name NOT LIKE \'ISEQ$$_%\' ESCAPE \'\\\') LOOP
            EXECUTE IMMEDIATE (\'DROP SEQUENCE "\' || replace(s.sequence_owner, \'"\', \'""\') || \'"."\' || replace(s.sequence_name, \'"\', \'""\') || \'"\');
            END LOOP;

            END;';

        $this->assertEquals($expected, $statement);
    }

    public function test_drop_all_views_uses_the_requested_schema()
    {
        $statement = $this->getGrammar()->compileDropAllViews('reporting');

        $this->assertStringContainsString("FROM all_views WHERE owner = 'REPORTING'", $statement);
        $this->assertStringContainsString("DROP VIEW \"' || replace(v.owner", $statement);
    }

    public function test_drop_all_types_uses_the_requested_schema()
    {
        $statement = $this->getGrammar()->compileDropAllTypes('reporting');

        $this->assertStringContainsString("FROM all_types WHERE owner = 'REPORTING'", $statement);
        $this->assertStringContainsString("DROP TYPE \"' || replace(t.owner", $statement);
    }

    public function test_compile_enable_foreign_key_constraints_selects_foreign_keys_by_owner()
    {
        $statement = $this->getGrammar()->compileEnableForeignKeyConstraints('username');

        $expected = 'begin
            for s in (
                SELECT \'alter table "\' || replace(fk.owner, \'"\', \'""\') || \'"."\' || replace(fk.table_name, \'"\', \'""\') || \'" enable constraint "\' || replace(fk.constraint_name, \'"\', \'""\') || \'"\' as statement
                FROM all_constraints fk
                WHERE fk.constraint_type = \'R\'
                  AND upper(fk.owner) = upper(\'username\')
                ORDER BY fk.table_name, fk.constraint_name
                )
                loop
                    execute immediate s.statement;
                end loop;
        end;';

        $this->assertEquals($expected, $statement);
    }

    public function test_compile_disable_foreign_key_constraints_selects_foreign_keys_by_owner()
    {
        $statement = $this->getGrammar()->compileDisableForeignKeyConstraints('username');

        $expected = 'begin
            for s in (
                SELECT \'alter table "\' || replace(fk.owner, \'"\', \'""\') || \'"."\' || replace(fk.table_name, \'"\', \'""\') || \'" disable constraint "\' || replace(fk.constraint_name, \'"\', \'""\') || \'"\' as statement
                FROM all_constraints fk
                WHERE fk.constraint_type = \'R\'
                  AND upper(fk.owner) = upper(\'username\')
                ORDER BY fk.table_name, fk.constraint_name
                )
                loop
                    execute immediate s.statement;
                end loop;
        end;';

        $this->assertEquals($expected, $statement);
    }

    public function test_compile_foreign_key_constraints_escapes_the_owner(): void
    {
        $statement = $this->getGrammar()->compileForeignKeyConstraints("tenant'o", 'ENABLE');

        $this->assertStringContainsString("upper(fk.owner) = upper('tenant''o')", $statement);
        $this->assertStringContainsString(' enable constraint ', $statement);
    }

    public function test_compile_foreign_key_constraints_rejects_an_unsupported_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported foreign key constraint action [drop].');

        $this->getGrammar()->compileForeignKeyConstraints('username', 'drop');
    }

    public function test_compile_tables_includes_current_user_table_storage()
    {
        $statement = $this->getGrammar($this->getConnection(serverVersion: '11g'))->compileTables('username');

        $this->assertStringContainsString('segments.bytes as "size"', $statement);
        $this->assertStringContainsString('from user_segments s', $statement);
        $this->assertStringContainsString('select st.owner as table_owner', $statement);
        $this->assertStringContainsString('on st.owner = user', $statement);
        $this->assertStringContainsString('on i.owner = user', $statement);
        $this->assertStringContainsString('on l.owner = user', $statement);
        $this->assertStringContainsString('on segments.owner = t.owner', $statement);
        $this->assertStringContainsString('join all_indexes i', $statement);
        $this->assertStringContainsString('join all_lobs l', $statement);
        $this->assertStringContainsString("s.segment_type in ('TABLE', 'TABLE PARTITION', 'TABLE SUBPARTITION')", $statement);
        $this->assertStringContainsString("s.segment_type in ('INDEX', 'INDEX PARTITION', 'INDEX SUBPARTITION')", $statement);
        $this->assertStringContainsString("s.segment_type in ('LOBSEGMENT', 'LOBINDEX', 'LOB PARTITION', 'LOB SUBPARTITION')", $statement);
        $this->assertStringContainsString("where upper(table_owner) = upper('username')", $statement);
        $this->assertStringContainsString("where upper(t.owner) = upper('username')", $statement);
        $this->assertStringContainsString('null as "collation"', $statement);
        $this->assertStringNotContainsString('all_segments', $statement);
        $this->assertStringNotContainsString('dba_segments', $statement);
        $this->assertDoesNotMatchRegularExpression('/\bs\.owner\b/', $statement);
        $this->assertStringNotContainsString('NLS_SORT', $statement);
    }

    public function test_compile_tables_accepts_multiple_schemas()
    {
        $statement = $this->getGrammar()->compileTables(['reporting', 'audit']);

        $this->assertStringContainsString("where upper(table_owner) in (upper('reporting'), upper('audit'))", $statement);
        $this->assertStringContainsString("where upper(t.owner) in (upper('reporting'), upper('audit'))", $statement);
        $this->assertStringContainsString('order by t.owner, t.table_name', $statement);
    }

    public function test_compile_tables_reports_table_default_collation_on_oracle_12c_release_2_and_newer()
    {
        $statement = $this->getGrammar($this->getConnection(serverVersion: '12cR2'))
            ->compileTables('username');

        $this->assertStringContainsString('lower(t.default_collation) as "collation"', $statement);
    }

    public function test_adding_generated_as()
    {
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs();
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default as identity )', $statements[0]);

        // With default on null
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs()->onNull();
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default on null as identity )', $statements[0]);

        // With always modifier
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs()->always();
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated always as identity )', $statements[0]);

        // With sequence options
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs('increment by 10 start with 100');
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default as identity (increment by 10 start with 100) )', $statements[0]);

        // With sequence options and on null
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs('increment by 10 start with 100')->onNull();
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default on null as identity (increment by 10 start with 100) )', $statements[0]);

        // With fluent starting value
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs()->startingValue(100);
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default as identity (start with 100) )', $statements[0]);

        // With fluent from alias
        $blueprint = new Blueprint($this->getConnection(), 'users');
        $blueprint->increments('foo')->generatedAs()->from(200);
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "USERS" add ( "FOO" number(10,0) generated by default as identity (start with 200) )', $statements[0]);
    }

    public function test_create_table_with_virtual_as()
    {
        $blueprint = new Blueprint($this->getConnection(), 'generated_columns');
        $blueprint->integer('value');
        $blueprint->integer('double_value')->virtualAs('"VALUE" * 2');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(
            'create table "GENERATED_COLUMNS" ( "VALUE" number(10,0) not null, "DOUBLE_VALUE" number(10,0) generated always as ("VALUE" * 2) virtual not null )',
            $statements[0]
        );
    }

    public function test_add_column_with_virtual_as()
    {
        $blueprint = new Blueprint($this->getConnection(), 'generated_columns');
        $blueprint->integer('double_value')->virtualAs('"VALUE" * 2');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(
            'alter table "GENERATED_COLUMNS" add ( "DOUBLE_VALUE" number(10,0) generated always as ("VALUE" * 2) virtual not null )',
            $statements[0]
        );
    }
}
