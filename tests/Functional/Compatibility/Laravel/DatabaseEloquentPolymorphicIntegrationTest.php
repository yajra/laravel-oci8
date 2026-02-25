<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentPolymorphicIntegrationTest extends LaravelTestCase
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
            $table->timestamps();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        $this->schema()->create('comments', function ($table) {
            $table->increments('id');
            $table->integer('commentable_id');
            $table->string('commentable_type');
            $table->integer('user_id');
            $table->text('body');
            $table->timestamps();
        });

        $this->schema()->create('likes', function ($table) {
            $table->increments('id');
            $table->integer('likeable_id');
            $table->string('likeable_type');
            $table->timestamps();
        });
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('users');
        $this->schema()->drop('posts');
        $this->schema()->drop('comments');
        $this->schema()->drop('likes');

        parent::tearDown();
    }

    public function test_it_loads_relationships_automatically()
    {
        $this->seedData();

        $like = TestLikeWithSingleWith::first();

        $this->assertTrue($like->relationLoaded('likeable'));
        $this->assertEquals(TestComment::first(), $like->likeable);
    }

    public function test_it_loads_chained_relationships_automatically()
    {
        $this->seedData();

        $like = TestLikeWithSingleWith::first();

        $this->assertTrue($like->likeable->relationLoaded('commentable'));
        $this->assertEquals(TestPost::first(), $like->likeable->commentable);
    }

    public function test_it_loads_nested_relationships_automatically()
    {
        $this->seedData();

        $like = TestLikeWithNestedWith::first();

        $this->assertTrue($like->relationLoaded('likeable'));
        $this->assertTrue($like->likeable->relationLoaded('owner'));

        $this->assertEquals(TestUser::first(), $like->likeable->owner);
    }

    public function test_it_loads_nested_relationships_on_demand()
    {
        $this->seedData();

        $like = TestLike::with('likeable.owner')->first();

        $this->assertTrue($like->relationLoaded('likeable'));
        $this->assertTrue($like->likeable->relationLoaded('owner'));

        $this->assertEquals(TestUser::first(), $like->likeable->owner);
    }

    public function test_it_loads_nested_morph_relationships_on_demand()
    {
        $this->seedData();

        TestPost::first()->likes()->create([]);

        $likes = TestLike::with('likeable.owner')->get()->loadMorph('likeable', [
            TestComment::class => ['commentable'],
            TestPost::class => 'comments',
        ]);

        $this->assertTrue($likes[0]->relationLoaded('likeable'));
        $this->assertTrue($likes[0]->likeable->relationLoaded('owner'));
        $this->assertTrue($likes[0]->likeable->relationLoaded('commentable'));

        $this->assertTrue($likes[1]->relationLoaded('likeable'));
        $this->assertTrue($likes[1]->likeable->relationLoaded('owner'));
        $this->assertTrue($likes[1]->likeable->relationLoaded('comments'));
    }

    public function test_it_loads_nested_morph_relationship_counts_on_demand()
    {
        $this->seedData();

        TestPost::first()->likes()->create([]);
        TestComment::first()->likes()->create([]);

        $likes = TestLike::with('likeable.owner')->get()->loadMorphCount('likeable', [
            TestComment::class => ['likes'],
            TestPost::class => 'comments',
        ]);

        $this->assertTrue($likes[0]->relationLoaded('likeable'));
        $this->assertTrue($likes[0]->likeable->relationLoaded('owner'));
        $this->assertEquals(2, $likes[0]->likeable->likes_count);

        $this->assertTrue($likes[1]->relationLoaded('likeable'));
        $this->assertTrue($likes[1]->likeable->relationLoaded('owner'));
        $this->assertEquals(1, $likes[1]->likeable->comments_count);

        $this->assertTrue($likes[2]->relationLoaded('likeable'));
        $this->assertTrue($likes[2]->likeable->relationLoaded('owner'));
        $this->assertEquals(2, $likes[2]->likeable->likes_count);
    }

    /**
     * Helpers...
     */
    protected function seedData()
    {
        $taylor = TestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $taylor->posts()->create(['title' => 'A title', 'body' => 'A body'])
            ->comments()->create(['body' => 'A comment body', 'user_id' => 1])
            ->likes()->create([]);
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
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

/**
 * Eloquent Models...
 */
class TestUser extends Eloquent
{
    protected $table = 'users';

    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }
}

/**
 * Eloquent Models...
 */
class TestPost extends Eloquent
{
    protected $table = 'posts';

    protected $guarded = [];

    public function comments()
    {
        return $this->morphMany(TestComment::class, 'commentable');
    }

    public function owner()
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function likes()
    {
        return $this->morphMany(TestLike::class, 'likeable');
    }
}

/**
 * Eloquent Models...
 */
class TestComment extends Eloquent
{
    protected $table = 'comments';

    protected $guarded = [];

    protected $with = ['commentable'];

    public function owner()
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function likes()
    {
        return $this->morphMany(TestLike::class, 'likeable');
    }
}

class TestLike extends Eloquent
{
    protected $table = 'likes';

    protected $guarded = [];

    public function likeable()
    {
        return $this->morphTo();
    }
}

class TestLikeWithSingleWith extends Eloquent
{
    protected $table = 'likes';

    protected $guarded = [];

    protected $with = ['likeable'];

    public function likeable()
    {
        return $this->morphTo();
    }
}

class TestLikeWithNestedWith extends Eloquent
{
    protected $table = 'likes';

    protected $guarded = [];

    protected $with = ['likeable.owner'];

    public function likeable()
    {
        return $this->morphTo();
    }
}
