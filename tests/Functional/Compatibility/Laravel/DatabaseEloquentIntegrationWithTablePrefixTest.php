<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Builder;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentIntegrationWithTablePrefixTest extends LaravelTestCase
{
    protected function createSchema()
    {
        $this->connection('default')->setTablePrefix('prefix_');

        $this->schema('default')->create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->timestamps();
        });

        $this->schema('default')->create('friends', function ($table) {
            $table->integer('user_id');
            $table->integer('friend_id');
        });

        $this->schema('default')->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        $this->schema('default')->create('photos', function ($table) {
            $table->increments('id');
            $table->morphs('imageable');
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        foreach (['default'] as $connection) {
            $this->schema($connection)->drop('users');
            $this->schema($connection)->drop('friends');
            $this->schema($connection)->drop('posts');
            $this->schema($connection)->drop('photos');
        }

        Relation::morphMap([], false);

        parent::tearDown();
    }

    public function test_basic_model_hydration()
    {
        EloquentTablePrefixTestUser::create(['email' => 'taylorotwell@gmail.com']);
        EloquentTablePrefixTestUser::create(['email' => 'abigailotwell@gmail.com']);

        $models = EloquentTablePrefixTestUser::fromQuery('SELECT * FROM prefix_users WHERE email = ?', ['abigailotwell@gmail.com']);

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(EloquentTablePrefixTestUser::class, $models[0]);
        $this->assertSame('abigailotwell@gmail.com', $models[0]->email);
        $this->assertCount(1, $models);
    }

    public function test_table_prefix_with_cloned_connection()
    {
        $originalConnection = $this->connection();
        $originalPrefix = $originalConnection->getTablePrefix();

        $clonedConnection = clone $originalConnection;
        $clonedConnection->setTablePrefix('cloned_');

        $this->assertSame($originalPrefix, $originalConnection->getTablePrefix());
        $this->assertSame('cloned_', $clonedConnection->getTablePrefix());

        $clonedConnection->getSchemaBuilder()->create('test_table', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        $this->assertTrue($clonedConnection->getSchemaBuilder()->hasTable('test_table'));
        $query = $clonedConnection->table('test_table')->toSql();
        $this->assertStringContainsStringIgnoringCase('cloned_test_table', $query);

        $clonedConnection->getSchemaBuilder()->drop('test_table');
    }

    public function test_query_grammar_uses_correct_prefix_after_cloning()
    {
        $originalConnection = $this->connection();

        $clonedConnection = clone $originalConnection;
        $clonedConnection->setTablePrefix('new_prefix_');

        $selectSql = $clonedConnection->table('users')->toSql();
        $this->assertStringContainsStringIgnoringCase('new_prefix_users', $selectSql);

        $insertSql = $clonedConnection->table('users')->toSql();
        $this->assertStringContainsStringIgnoringCase('new_prefix_users', $insertSql);

        $updateSql = $clonedConnection->table('users')->where('id', 1)->toSql();
        $this->assertStringContainsStringIgnoringCase('new_prefix_users', $updateSql);

        $deleteSql = $clonedConnection->table('users')->where('id', 1)->toSql();
        $this->assertStringContainsStringIgnoringCase('new_prefix_users', $deleteSql);

        $originalSql = $originalConnection->table('users')->toSql();
        $this->assertStringContainsStringIgnoringCase('prefix_users', $originalSql);
        $this->assertStringNotContainsStringIgnoringCase('new_prefix_users', $originalSql);
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return Connection
     */
    protected function connection($connection = 'default')
    {
        return Eloquent::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class EloquentTablePrefixTestUser extends Eloquent
{
    protected $table = 'users';

    protected $guarded = [];
}
