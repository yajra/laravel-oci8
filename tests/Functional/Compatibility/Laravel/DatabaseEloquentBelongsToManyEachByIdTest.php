<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentBelongsToManyEachByIdTest extends LaravelTestCase
{
    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email')->unique();
        });

        $this->schema()->create('articles', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        $this->schema()->create('article_user', function ($table) {
            $table->increments('id');
            $table->integer('article_id')->unsigned();
            $table->foreign('article_id')->references('id')->on('articles');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function test_belongs_to_each_by_id()
    {
        $this->seedData();

        $user = BelongsToManyEachByIdTestTestUser::query()->first();
        $i = 0;

        $user->articles()->eachById(function (BelongsToManyEachByIdTestTestArticle $model) use (&$i) {
            $i++;
            $this->assertEquals($i, $model->id);
        });

        $this->assertSame(3, $i);
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('article_user');
        $this->schema()->drop('users');
        $this->schema()->drop('articles');

        parent::tearDown();
    }

    /**
     * Helpers...
     */
    protected function seedData()
    {
        $user = BelongsToManyEachByIdTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        BelongsToManyEachByIdTestTestArticle::query()->insert([
            ['id' => 1, 'title' => 'Another title'],
            ['id' => 2, 'title' => 'Another title'],
            ['id' => 3, 'title' => 'Another title'],
        ]);

        $user->articles()->sync([3, 1, 2]);
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

class BelongsToManyEachByIdTestTestUser extends Eloquent
{
    protected $table = 'users';

    protected $fillable = ['id', 'email'];

    public $timestamps = false;

    public function articles()
    {
        return $this->belongsToMany(BelongsToManyEachByIdTestTestArticle::class, 'article_user', 'user_id', 'article_id');
    }
}

class BelongsToManyEachByIdTestTestArticle extends Eloquent
{
    protected $table = 'articles';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'title'];
}
