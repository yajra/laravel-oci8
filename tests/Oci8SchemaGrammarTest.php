<?php

use Mockery as m;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;

class Oci8SchemaGrammarTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testBasicCreateTable()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table users ( id number(10,0) not null, email varchar2(255) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithReservedWords()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('group');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table users ( id number(10,0) not null, "group" varchar2(255) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    protected function getConnection()
    {
        return m::mock('Illuminate\Database\Connection');
    }

    public function getGrammar()
    {
        return new Yajra\Oci8\Schema\Grammars\OracleGrammar;
    }

    public function testBasicCreateTableWithPrimary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table users ( id number(10,0) not null, email varchar2(255) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrimaryAndForeignKeys()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table prefix_users ( id number(10,0) not null, email varchar2(255) not null, foo_id number(10,0) not null, constraint users_foo_id_fk foreign key ( foo_id ) references prefix_orders ( id ), constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithDefaultValueAndIsNotNull()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email')->default('user@test.com');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table users ( id number(10,0) not null, email varchar2(255) default \'user@test.com\' not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');
        $blueprint->setTablePrefix('prefix_');
        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table prefix_users ( id number(10,0) not null, email varchar2(255) not null, constraint prefix_users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefixAndPrimary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');
        $blueprint->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table prefix_users ( id number(10,0) not null, email varchar2(255) not null, constraint prefix_users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefixPrimaryAndForeignKeys()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');
        $blueprint->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table prefix_users ( id number(10,0) not null, email varchar2(255) not null, foo_id number(10,0) not null, constraint users_foo_id_fk foreign key ( foo_id ) references prefix_orders ( id ), constraint prefix_users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefixPrimaryAndForeignKeysWithCascadeDelete()
    {
        $blueprint = new Blueprint('users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('create table prefix_users ( id number(10,0) not null, email varchar2(255) not null, foo_id number(10,0) not null, constraint users_foo_id_fk foreign key ( foo_id ) references prefix_orders ( id ) on delete cascade, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicAlterTable()
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( id number(10,0) not null, email varchar2(255) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicAlterTableWithPrimary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( id number(10,0) not null, email varchar2(255) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicAlterTableWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->setTablePrefix('prefix_');
        $blueprint->increments('id');
        $blueprint->string('email');

        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table prefix_users add ( id number(10,0) not null, email varchar2(255) not null, constraint prefix_users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testBasicAlterTableWithPrefixAndPrimary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->setTablePrefix('prefix_');
        $blueprint->increments('id');
        $blueprint->string('email');

        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table prefix_users add ( id number(10,0) not null, email varchar2(255) not null, constraint prefix_users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testDropTable()
    {
        $blueprint = new Blueprint('users');
        $blueprint->drop();
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop table users', $statements[0]);
    }

    public function testCompileTableExistsMethod()
    {
        $grammar  = $this->getGrammar();
        $expected = 'select * from user_tables where upper(table_name) = upper(?)';
        $sql      = $grammar->compileTableExists();
        $this->assertEquals($expected, $sql);
    }

    public function testCompileColumnExistsMethod()
    {
        $grammar  = $this->getGrammar();
        $expected = 'select column_name from user_tab_columns where table_name = upper(\'test_table\')';
        $sql      = $grammar->compileColumnExists("test_table");
        $this->assertEquals($expected, $sql);
    }

    public function testDropTableWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->setTablePrefix('prefix_');
        $blueprint->drop();

        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $statements = $blueprint->toSql($this->getConnection(), $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop table prefix_users', $statements[0]);
    }

    public function testDropColumn()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropColumn('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop ( foo )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->dropColumn(['foo', 'bar']);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop ( foo, bar )', $statements[0]);
    }

    public function testDropPrimary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropPrimary('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop constraint foo', $statements[0]);
    }

    public function testDropUnique()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropUnique('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop constraint foo', $statements[0]);
    }

    public function testDropIndex()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropIndex('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop index foo', $statements[0]);
    }

    public function testDropForeign()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropForeign('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop constraint foo', $statements[0]);
    }

    public function testDropTimestamps()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropTimestamps();
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users drop ( created_at, updated_at )', $statements[0]);
    }

    public function testRenameTable()
    {
        $blueprint = new Blueprint('users');
        $blueprint->rename('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users rename to foo', $statements[0]);
    }

    public function testRenameTableWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->rename('foo');
        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');
        $statements = $blueprint->toSql($this->getConnection(), $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table prefix_users rename to prefix_foo', $statements[0]);
    }

    public function testAddingPrimaryKey()
    {
        $blueprint = new Blueprint('users');
        $blueprint->primary('foo', 'bar');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint bar primary key (foo)', $statements[0]);
    }

    public function testAddingPrimaryKeyWithConstraintAutomaticName()
    {
        $blueprint = new Blueprint('users');
        $blueprint->primary('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint users_foo_pk primary key (foo)', $statements[0]);
    }

    public function testAddingPrimaryKeyWithConstraintAutomaticNameGreaterThanThirtyCharacters()
    {
        $blueprint = new Blueprint('users');
        $blueprint->primary('reset_password_secret_code');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint users_reset_password_secret_co primary key (reset_password_secret_code)',
            $statements[0]);
    }

    public function testAddingUniqueKey()
    {
        $blueprint = new Blueprint('users');
        $blueprint->unique('foo', 'bar');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint bar unique ( foo )', $statements[0]);
    }

    public function testAddingDefinedUniqueKeyWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->setTablePrefix('prefix_');
        $blueprint->unique('foo', 'bar');

        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $statements = $blueprint->toSql($this->getConnection(), $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table prefix_users add constraint bar unique ( foo )', $statements[0]);
    }

    public function testAddingGeneratedUniqueKeyWithPrefix()
    {
        $blueprint = new Blueprint('users');
        $blueprint->setTablePrefix('prefix_');
        $blueprint->unique('foo');

        $grammar = $this->getGrammar();
        $grammar->setTablePrefix('prefix_');

        $statements = $blueprint->toSql($this->getConnection(), $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table prefix_users add constraint prefix_users_foo_uk unique ( foo )',
            $statements[0]);
    }

    public function testAddingIndex()
    {
        $blueprint = new Blueprint('users');
        $blueprint->index(['foo', 'bar'], 'baz');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));

        $this->assertEquals('create index baz on users ( foo, bar )', $statements[0]);
    }

    public function testAddingForeignKey()
    {
        $blueprint = new Blueprint('users');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint users_foo_id_fk foreign key ( foo_id ) references orders ( id )',
            $statements[0]);
    }

    public function testAddingForeignKeyWithCascadeDelete()
    {
        $blueprint = new Blueprint('users');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add constraint users_foo_id_fk foreign key ( foo_id ) references orders ( id ) on delete cascade',
            $statements[0]);
    }

    public function testAddingIncrementingID()
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( id number(10,0) not null, constraint users_id_pk primary key ( id ) )',
            $statements[0]);
    }

    public function testAddingString()
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(255) not null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(100) not null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(100) default \'bar\' null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->string('foo', 100)
                  ->nullable()
                  ->default(new Illuminate\Database\Query\Expression('CURRENT TIMESTAMP'));
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(100) default CURRENT TIMESTAMP null )',
            $statements[0]);
    }

    public function testAddingLongText()
    {
        $blueprint = new Blueprint('users');
        $blueprint->longText('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo clob not null )', $statements[0]);
    }

    public function testAddingMediumText()
    {
        $blueprint = new Blueprint('users');
        $blueprint->mediumText('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo clob not null )', $statements[0]);
    }

    public function testAddingText()
    {
        $blueprint = new Blueprint('users');
        $blueprint->text('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo clob not null )', $statements[0]);
    }

    public function testAddingChar()
    {
        $blueprint = new Blueprint('users');
        $blueprint->char('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo char(255) not null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->char('foo', 1);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo char(1) not null )', $statements[0]);
    }

    public function testAddingBigInteger()
    {
        $blueprint = new Blueprint('users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(19,0) not null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(19,0) not null, constraint users_foo_pk primary key ( foo ) )',
            $statements[0]);
    }

    public function testAddingInteger()
    {
        $blueprint = new Blueprint('users');
        $blueprint->integer('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(10,0) not null )', $statements[0]);

        $blueprint = new Blueprint('users');
        $blueprint->integer('foo', true);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(10,0) not null, constraint users_foo_pk primary key ( foo ) )',
            $statements[0]);
    }

    public function testAddingMediumInteger()
    {
        $blueprint = new Blueprint('users');
        $blueprint->mediumInteger('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(7,0) not null )', $statements[0]);
    }

    public function testAddingSmallInteger()
    {
        $blueprint = new Blueprint('users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(5,0) not null )', $statements[0]);
    }

    public function testAddingTinyInteger()
    {
        $blueprint = new Blueprint('users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(3,0) not null )', $statements[0]);
    }

    public function testAddingFloat()
    {
        $blueprint = new Blueprint('users');
        $blueprint->float('foo', 5, 2);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(5, 2) not null )', $statements[0]);
    }

    public function testAddingDouble()
    {
        $blueprint = new Blueprint('users');
        $blueprint->double('foo', 5, 2);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(5, 2) not null )', $statements[0]);
    }

    public function testAddingDecimal()
    {
        $blueprint = new Blueprint('users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo number(5, 2) not null )', $statements[0]);
    }

    public function testAddingBoolean()
    {
        $blueprint = new Blueprint('users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo char(1) not null )', $statements[0]);
    }

    public function testAddingEnum()
    {
        $blueprint = new Blueprint('users');
        $blueprint->enum('foo', ['bar', 'baz']);
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(255) not null check (foo in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function testAddingEnumWithDefaultValue()
    {
        $blueprint = new Blueprint('users');
        $blueprint->enum('foo', ['bar', 'baz'])->default('bar');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo varchar2(255) default \'bar\' not null check (foo in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function testAddingDate()
    {
        $blueprint = new Blueprint('users');
        $blueprint->date('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo date not null )', $statements[0]);
    }

    public function testAddingDateTime()
    {
        $blueprint = new Blueprint('users');
        $blueprint->dateTime('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo date not null )', $statements[0]);
    }

    public function testAddingTime()
    {
        $blueprint = new Blueprint('users');
        $blueprint->time('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo date not null )', $statements[0]);
    }

    public function testAddingTimeStamp()
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestamp('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo timestamp not null )', $statements[0]);
    }

    public function testAddingTimeStampTz()
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestampTz('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo timestamp with time zone not null )', $statements[0]);
    }

    public function testAddingNullableTimeStamps()
    {
        $blueprint = new Blueprint('users');
        $blueprint->nullableTimestamps();
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( created_at timestamp null, updated_at timestamp null )',
            $statements[0]);
    }

    public function testAddingTimeStamps()
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestamps();
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( created_at timestamp null, updated_at timestamp null )',
            $statements[0]);
    }

    public function testAddingTimeStampTzs()
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestampsTz();
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( created_at timestamp with time zone null, updated_at timestamp with time zone null )',
            $statements[0]);
    }

    public function testAddingBinary()
    {
        $blueprint = new Blueprint('users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql($this->getConnection(), $this->getGrammar());

        $this->assertEquals(1, count($statements));
        $this->assertEquals('alter table users add ( foo blob not null )', $statements[0]);
    }
}
