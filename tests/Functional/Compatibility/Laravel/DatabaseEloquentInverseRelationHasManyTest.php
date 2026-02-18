<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentInverseRelationHasManyTest extends LaravelTestCase
{
    protected function createSchema()
    {
        $this->schema()->create('test_users', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema()->create('test_posts', function ($table) {
            $table->increments('id');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('test_posts');
        $this->schema()->drop('test_users');

        parent::tearDown();
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_lazy_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::all();

        foreach ($users as $user) {
            $this->assertFalse($user->relationLoaded('posts'));
            foreach ($user->posts as $post) {
                $this->assertTrue($post->relationLoaded('user'));
                $this->assertSame($user, $post->user);
            }
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_eager_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::with('posts')->get();

        foreach ($users as $user) {
            $posts = $user->getRelation('posts');

            foreach ($posts as $post) {
                $this->assertTrue($post->relationLoaded('user'));
                $this->assertSame($user, $post->user);
            }
        }
    }

    public function test_has_latest_of_many_inverse_relation_is_properly_set_to_parent_when_lazy_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::all();

        foreach ($users as $user) {
            $this->assertFalse($user->relationLoaded('lastPost'));
            $post = $user->lastPost;

            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_latest_of_many_inverse_relation_is_properly_set_to_parent_when_eager_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::with('lastPost')->get();

        foreach ($users as $user) {
            $post = $user->getRelation('lastPost');

            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_one_of_many_inverse_relation_is_properly_set_to_parent_when_lazy_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::all();

        foreach ($users as $user) {
            $this->assertFalse($user->relationLoaded('firstPost'));
            $post = $user->firstPost;

            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_one_of_many_inverse_relation_is_properly_set_to_parent_when_eager_loaded()
    {
        HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
        $users = HasManyInverseUserModel::with('firstPost')->get();

        foreach ($users as $user) {
            $post = $user->getRelation('firstPost');

            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_making_many()
    {
        $user = HasManyInverseUserModel::create();

        $posts = $user->posts()->makeMany(array_fill(0, 3, []));

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_creating_many()
    {
        $user = HasManyInverseUserModel::create();

        $posts = $user->posts()->createMany(array_fill(0, 3, []));

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_creating_many_quietly()
    {
        $user = HasManyInverseUserModel::create();

        $posts = $user->posts()->createManyQuietly(array_fill(0, 3, []));

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_saving_many()
    {
        $user = HasManyInverseUserModel::create();

        $posts = array_fill(0, 3, new HasManyInversePostModel);

        $user->posts()->saveMany($posts);

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }

    public function test_has_many_inverse_relation_is_properly_set_to_parent_when_updating_many()
    {
        $user = HasManyInverseUserModel::create();

        $posts = HasManyInversePostModel::factory()->count(3)->create();

        foreach ($posts as $post) {
            $this->assertTrue($user->isNot($post->user));
        }

        $user->posts()->saveMany($posts);

        foreach ($posts as $post) {
            $this->assertSame($user, $post->user);
        }
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection($connection = 'default')
    {
        return Eloquent::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class HasManyInverseUserModel extends Model
{
    use HasFactory;

    protected $table = 'test_users';

    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new HasManyInverseUserModelFactory;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(HasManyInversePostModel::class, 'user_id')->inverse('user');
    }

    public function lastPost(): HasOne
    {
        return $this->hasOne(HasManyInversePostModel::class, 'user_id')->latestOfMany()->inverse('user');
    }

    public function firstPost(): HasOne
    {
        return $this->posts()->one();
    }
}

class HasManyInverseUserModelFactory extends Factory
{
    protected $model = HasManyInverseUserModel::class;

    public function definition()
    {
        return [];
    }

    public function withPosts(int $count = 3)
    {
        return $this->afterCreating(function (HasManyInverseUserModel $model) use ($count) {
            HasManyInversePostModel::factory()->recycle($model)->count($count)->create();
        });
    }
}

class HasManyInversePostModel extends Model
{
    use HasFactory;

    protected $table = 'test_posts';

    protected $fillable = ['id', 'user_id'];

    protected static function newFactory()
    {
        return new HasManyInversePostModelFactory;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(HasManyInverseUserModel::class, 'user_id');
    }
}

class HasManyInversePostModelFactory extends Factory
{
    protected $model = HasManyInversePostModel::class;

    public function definition()
    {
        return [
            'user_id' => HasManyInverseUserModel::factory(),
        ];
    }
}
