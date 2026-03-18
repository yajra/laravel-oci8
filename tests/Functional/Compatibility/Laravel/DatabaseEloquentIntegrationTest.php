<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use DateTimeInterface;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\AbstractPaginator as Paginator;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Models\Post;
use Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Models\User;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentIntegrationTest extends LaravelTestCase
{
    protected function createSchema()
    {
        $this->schema('default')->create('test_orders', function ($table) {
            $table->increments('id');
            $table->string('item_type');
            $table->integer('item_id');
            $table->timestamps();
        });

        $this->schema('default')->create('with_json', function ($table) {
            $table->increments('id');
            $table->text('json')->default(json_encode([]));
        });

        $this->schema('second_connection')->create('test_items', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        if (! ($this->connection('default')->getDriverName() === 'oracle'
            && $this->connection('default')->isVersionBelow('12c'))) {
            $this->schema('default')->create('users_with_space_in_column_name', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email address');
                $table->timestamps();
            });
        }

        $this->schema()->create('users_having_uuids', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->tinyInteger('role');
            $table->string('role_string');
        });

        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->create('users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email');
                $table->timestamp('birthday', 6)->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('unique_users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                // Unique constraint will be applied only for non-null values
                $table->string('screen_name')->nullable()->unique();
                $table->string('email')->unique();
                $table->timestamp('birthday', 6)->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('friends', function ($table) {
                $table->integer('user_id');
                $table->integer('friend_id');
                $table->integer('friend_level_id')->nullable();
            });

            $this->schema($connection)->create('posts', function ($table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->integer('parent_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('comments', function ($table) {
                $table->increments('id');
                $table->integer('post_id');
                $table->string('content');
                $table->timestamps();
            });

            $this->schema($connection)->create('friend_levels', function ($table) {
                $table->increments('id');
                $table->string('level');
                $table->timestamps();
            });

            $this->schema($connection)->create('photos', function ($table) {
                $table->increments('id');
                $table->morphs('imageable');
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('soft_deleted_users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email');
                $table->timestamps();
                $table->softDeletes();
            });

            $this->schema($connection)->create('tags', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });

            $this->schema($connection)->create('taggables', function ($table) {
                $table->integer('tag_id');
                $table->morphs('taggable');
                $table->string('taxonomy')->nullable();
            });

            $this->schema($connection)->create('categories', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('parent_id')->nullable();
                $table->timestamps();
            });

            $this->schema($connection)->create('achievements', function ($table) {
                $table->increments('id');
                $table->integer('status')->nullable();
            });

            if (! ($this->connection('default')->getDriverName() === 'oracle'
                && $this->connection('default')->isVersionBelow('12c'))) {
                $this->schema($connection)->create('eloquent_test_achievement_eloquent_test_user', function ($table) {
                    $table->integer('eloquent_test_achievement_id');
                    $table->integer('eloquent_test_user_id');
                });
            }
        }

        $this->schema($connection)->create('non_incrementing_users', function ($table) {
            $table->string('name')->nullable();
        });
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->dropIfExists('eloquent_test_achievement_eloquent_test_user');
            $this->schema($connection)->dropIfExists('achievements');
            $this->schema($connection)->dropIfExists('categories');
            $this->schema($connection)->dropIfExists('taggables');
            $this->schema($connection)->dropIfExists('tags');
            $this->schema($connection)->dropIfExists('soft_deleted_users');
            $this->schema($connection)->dropIfExists('photos');
            $this->schema($connection)->dropIfExists('friend_levels');
            $this->schema($connection)->dropIfExists('comments');
            $this->schema($connection)->dropIfExists('posts');
            $this->schema($connection)->dropIfExists('friends');
            $this->schema($connection)->dropIfExists('unique_users');
            $this->schema($connection)->dropIfExists('users');
        }
        $this->schema('default')->dropIfExists('users_having_uuids');
        $this->schema('default')->dropIfExists('users_with_space_in_column_name');
        $this->schema('default')->dropIfExists('with_json');
        $this->schema('default')->dropIfExists('test_orders');
        $this->schema('second_connection')->dropIfExists('non_incrementing_users');
        $this->schema('second_connection')->dropIfExists('test_items');

        Relation::morphMap([], false);
        Eloquent::unsetConnectionResolver();

        Carbon::setTestNow(null);
        Str::createUuidsNormally();
        DB::flushQueryLog();

        parent::tearDown();
    }

    /**
     * Tests...
     */
    public function test_basic_model_retrieval()
    {
        EloquentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'abigailotwell@gmail.com']]);

        $this->assertEquals(2, EloquentTestUser::count());

        $this->assertFalse(EloquentTestUser::where('email', 'taylorotwell@gmail.com')->doesntExist());
        $this->assertTrue(EloquentTestUser::where('email', 'mohamed@laravel.com')->doesntExist());

        $model = EloquentTestUser::where('email', 'taylorotwell@gmail.com')->first();
        $this->assertSame('taylorotwell@gmail.com', $model->email);
        $this->assertTrue(isset($model->email));
        $this->assertTrue(isset($model->friends));

        $model = EloquentTestUser::find(1);
        $this->assertInstanceOf(EloquentTestUser::class, $model);
        $this->assertEquals(1, $model->id);

        $model = EloquentTestUser::find(2);
        $this->assertInstanceOf(EloquentTestUser::class, $model);
        $this->assertEquals(2, $model->id);

        $missing = EloquentTestUser::find(3);
        $this->assertNull($missing);

        $collection = EloquentTestUser::find([]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);

        $collection = EloquentTestUser::find([1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);

        $models = EloquentTestUser::where('id', 1)->cursor();
        foreach ($models as $model) {
            $this->assertEquals(1, $model->id);
            $this->assertSame('default', $model->getConnectionName());
        }

        $records = DB::table('users')->where('id', 1)->cursor();
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }

        $records = DB::cursor('select * from users where id = ?', [1]);
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }
    }

    public function test_basic_model_collection_retrieval()
    {
        EloquentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'abigailotwell@gmail.com']]);

        $models = EloquentTestUser::oldest('id')->get();

        $this->assertCount(2, $models);
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
    }

    public function test_paginated_model_collection_retrieval()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertCount(1, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
    }

    public function test_paginated_model_collection_retrieval_using_callable_per_page()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(3, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[2]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertSame('foo@gmail.com', $models[2]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);

        EloquentTestUser::create(['id' => 4, 'email' => 'bar@gmail.com']);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(function ($total) {
            return $total <= 3 ? 3 : 2;
        });

        $this->assertCount(2, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
        $this->assertSame('bar@gmail.com', $models[1]->email);
    }

    public function test_paginated_model_collection_retrieval_when_no_elements()
    {
        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertCount(0, $models);
    }

    public function test_paginated_model_collection_retrieval_when_no_elements_and_default_per_page()
    {
        $models = EloquentTestUser::oldest('id')->paginate();

        $this->assertCount(0, $models);
        $this->assertInstanceOf(LengthAwarePaginator::class, $models);
    }

    public function test_count_for_pagination_with_grouping()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
            ['id' => 4, 'email' => 'foo@gmail.com'],
        ]);

        $query = EloquentTestUser::select('email')->groupBy('email')->getQuery();

        $this->assertEquals(3, $query->getCountForPagination());
    }

    // todo: fix this test
    //    public function test_count_for_pagination_with_grouping_and_sub_selects()
    //    {
    //        EloquentTestUser::insert([
    //            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
    //            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
    //            ['id' => 3, 'email' => 'foo@gmail.com'],
    //            ['id' => 4, 'email' => 'foo@gmail.com'],
    //        ]);
    //        $user1 = EloquentTestUser::find(1);
    //
    //        $user1->friends()->create(['id' => 5, 'email' => 'friend@gmail.com']);
    //
    //        $query = EloquentTestUser::select([
    //            'id',
    //            'friends_count' => EloquentTestUser::whereColumn('friend_id', 'user_id')->count(),
    //        ])->groupBy('email')->getQuery();
    //
    //        $this->assertEquals(4, $query->getCountForPagination());
    //    }

    public function test_cursor_paginated_model_collection_retrieval()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            $secondParams = ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        CursorPaginator::currentCursorResolver(function () {
            return null;
        });
        $models = EloquentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertTrue($models->hasMorePages());
        $this->assertTrue($models->hasPages());

        CursorPaginator::currentCursorResolver(function () use ($secondParams) {
            return new Cursor($secondParams);
        });
        $models = EloquentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(1, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertSame('foo@gmail.com', $models[0]->email);
        $this->assertFalse($models->hasMorePages());
        $this->assertTrue($models->hasPages());
    }

    public function test_previous_cursor_paginated_model_collection_retrieval()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
            $thirdParams = ['id' => 3, 'email' => 'foo@gmail.com'],
        ]);

        CursorPaginator::currentCursorResolver(function () use ($thirdParams) {
            return new Cursor($thirdParams, false);
        });
        $models = EloquentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(2, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $models[1]);
        $this->assertSame('taylorotwell@gmail.com', $models[0]->email);
        $this->assertSame('abigailotwell@gmail.com', $models[1]->email);
        $this->assertTrue($models->hasMorePages());
        $this->assertTrue($models->hasPages());
    }

    public function test_cursor_paginated_model_collection_retrieval_when_no_elements()
    {
        CursorPaginator::currentCursorResolver(function () {
            return null;
        });
        $models = EloquentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(0, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);

        Paginator::currentPageResolver(function () {
            return new Cursor(['id' => 1]);
        });
        $models = EloquentTestUser::oldest('id')->cursorPaginate(2);

        $this->assertCount(0, $models);
    }

    public function test_cursor_paginated_model_collection_retrieval_when_no_elements_and_default_per_page()
    {
        $models = EloquentTestUser::oldest('id')->cursorPaginate();

        $this->assertCount(0, $models);
        $this->assertInstanceOf(CursorPaginator::class, $models);
    }

    public function test_first_or_new()
    {
        $user1 = EloquentTestUser::firstOrNew(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro']
        );

        $this->assertSame('Nuno Maduro', $user1->name);
    }

    public function test_first_or_create()
    {
        $user1 = EloquentTestUser::firstOrCreate(['email' => 'taylorotwell@gmail.com']);

        $this->assertSame('taylorotwell@gmail.com', $user1->email);
        $this->assertNull($user1->name);

        $user2 = EloquentTestUser::firstOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = EloquentTestUser::firstOrCreate(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );

        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertSame('abigailotwell@gmail.com', $user3->email);
        $this->assertSame('Abigail Otwell', $user3->name);

        $user4 = EloquentTestUser::firstOrCreate(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@laravel.com']
        );

        $this->assertSame('Nuno Maduro', $user4->name);
    }

    public function test_create_or_first()
    {
        $user1 = EloquentTestUniqueUser::createOrFirst(['email' => 'taylorotwell@gmail.com']);

        $this->assertSame('taylorotwell@gmail.com', $user1->email);
        $this->assertNull($user1->name);

        $user2 = EloquentTestUniqueUser::createOrFirst(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = EloquentTestUniqueUser::createOrFirst(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );

        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertSame('abigailotwell@gmail.com', $user3->email);
        $this->assertSame('Abigail Otwell', $user3->name);

        $user4 = EloquentTestUniqueUser::createOrFirst(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@laravel.com']
        );

        $this->assertSame('Nuno Maduro', $user4->name);
    }

    public function test_create_or_first_non_attribute_field_violation()
    {
        // 'email' and 'screen_name' are unique and independent of each other.
        EloquentTestUniqueUser::create([
            'email' => 'taylorotwell+foo@gmail.com',
            'screen_name' => '@taylorotwell',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Although 'email' is expected to be unique and is passed as $attributes,
        // if the 'screen_name' attribute listed in non-unique $values causes a violation,
        // a UniqueConstraintViolationException should be thrown.
        EloquentTestUniqueUser::createOrFirst(
            ['email' => 'taylorotwell+bar@gmail.com'],
            [
                'screen_name' => '@taylorotwell',
            ]
        );
    }

    public function test_create_or_first_within_transaction()
    {
        $user1 = EloquentTestUniqueUser::create(['email' => 'taylorotwell@gmail.com']);

        DB::transaction(function () use ($user1) {
            $user2 = EloquentTestUniqueUser::createOrFirst(
                ['email' => 'taylorotwell@gmail.com'],
                ['name' => 'Taylor Otwell']
            );

            $this->assertEquals($user1->id, $user2->id);
            $this->assertSame('taylorotwell@gmail.com', $user2->email);
            $this->assertNull($user2->name);
        });
    }

    public function test_update_or_create()
    {
        $user1 = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user2 = EloquentTestUser::updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertSame('Taylor Otwell', $user2->name);

        $user3 = EloquentTestUser::updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertSame('Mohamed Said', $user3->name);
        $this->assertEquals(2, EloquentTestUser::count());
    }

    public function test_update_or_create_on_different_connection()
    {
        EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        EloquentTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        EloquentTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertEquals(1, EloquentTestUser::count());
        $this->assertEquals(2, EloquentTestUser::on('second_connection')->count());
    }

    public function test_check_and_create_methods_on_multi_connections()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::on('second_connection')->find(
            EloquentTestUser::on('second_connection')->insert(['id' => 2, 'email' => 'themsaid@gmail.com'])
        );

        $user1 = EloquentTestUser::on('second_connection')->findOrNew(1);
        $user2 = EloquentTestUser::on('second_connection')->findOrNew(2);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());

        $user1 = EloquentTestUser::on('second_connection')->firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $user2 = EloquentTestUser::on('second_connection')->firstOrNew(['email' => 'themsaid@gmail.com']);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());

        $this->assertEquals(1, EloquentTestUser::on('second_connection')->count());
        $user1 = EloquentTestUser::on('second_connection')->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
        $user2 = EloquentTestUser::on('second_connection')->firstOrCreate(['email' => 'themsaid@gmail.com']);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());
        $this->assertEquals(2, EloquentTestUser::on('second_connection')->count());
    }

    public function test_creating_model_with_empty_attributes()
    {
        $model = EloquentTestNonIncrementing::create([]);

        $this->assertFalse($model->exists);
        $this->assertFalse($model->wasRecentlyCreated);
    }

    public function test_chunk()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->assertCount(1, $users);
                $this->assertSame('Third', $users[0]->name);
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function test_chunks_with_limits_where_limit_is_less_than_total()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->limit(2)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function test_chunks_with_limits_where_limit_is_more_than_total()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->limit(10)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } elseif ($page === 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Third', $users[0]->name);
            } else {
                $this->fail('Should have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function test_chunks_with_offset()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->offset(1)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Second', $users[0]->name);
                $this->assertSame('Third', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function test_chunks_with_offset_where_more_than_total()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->offset(3)->chunk(2, function () use (&$chunks) {
            $chunks++;
        });

        $this->assertEquals(0, $chunks);
    }

    public function test_chunks_with_limits_and_offsets()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
            ['name' => 'Fourth', 'email' => 'fourth@example.com'],
            ['name' => 'Fifth', 'email' => 'fifth@example.com'],
            ['name' => 'Sixth', 'email' => 'sixth@example.com'],
            ['name' => 'Seventh', 'email' => 'seventh@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->orderBy('id', 'asc')->offset(2)->limit(3)->chunk(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Third', $users[0]->name);
                $this->assertSame('Fourth', $users[1]->name);
            } elseif ($page == 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Fifth', $users[0]->name);
            } else {
                $this->fail('Should only have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function test_chunk_by_id_with_limits()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->limit(2)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('First', $users[0]->name);
                $this->assertSame('Second', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function test_chunk_by_id_with_offsets()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->offset(1)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Second', $users[0]->name);
                $this->assertSame('Third', $users[1]->name);
            } else {
                $this->fail('Should only have had one page.');
            }

            $chunks++;
        });

        $this->assertEquals(1, $chunks);
    }

    public function test_chunk_by_id_with_limits_and_offsets()
    {
        EloquentTestUser::insert([
            ['name' => 'First', 'email' => 'first@example.com'],
            ['name' => 'Second', 'email' => 'second@example.com'],
            ['name' => 'Third', 'email' => 'third@example.com'],
            ['name' => 'Fourth', 'email' => 'fourth@example.com'],
            ['name' => 'Fifth', 'email' => 'fifth@example.com'],
            ['name' => 'Sixth', 'email' => 'sixth@example.com'],
            ['name' => 'Seventh', 'email' => 'seventh@example.com'],
        ]);

        $chunks = 0;

        EloquentTestUser::query()->offset(2)->limit(3)->chunkById(2, function (Collection $users, $page) use (&$chunks) {
            if ($page == 1) {
                $this->assertCount(2, $users);
                $this->assertSame('Third', $users[0]->name);
                $this->assertSame('Fourth', $users[1]->name);
            } elseif ($page == 2) {
                $this->assertCount(1, $users);
                $this->assertSame('Fifth', $users[0]->name);
            } else {
                $this->fail('Should only have had two pages.');
            }

            $chunks++;
        });

        $this->assertEquals(2, $chunks);
    }

    public function test_chunk_by_id_with_non_incrementing_key()
    {
        EloquentTestNonIncrementingSecond::insert([
            ['name' => ' First'],
            ['name' => ' Second'],
            ['name' => ' Third'],
        ]);

        $i = 0;
        EloquentTestNonIncrementingSecond::query()->chunkById(2, function (Collection $users) use (&$i) {
            if (! $i) {
                $this->assertSame(' First', $users[0]->name);
                $this->assertSame(' Second', $users[1]->name);
            } else {
                $this->assertSame(' Third', $users[0]->name);
            }
            $i++;
        }, 'name');
        $this->assertEquals(2, $i);
    }

    public function test_each_by_id_with_non_incrementing_key()
    {
        EloquentTestNonIncrementingSecond::insert([
            ['name' => ' First'],
            ['name' => ' Second'],
            ['name' => ' Third'],
        ]);

        $users = [];
        EloquentTestNonIncrementingSecond::query()->eachById(
            function (EloquentTestNonIncrementingSecond $user, $i) use (&$users) {
                $users[] = [$user->name, $i];
            }, 2, 'name');
        $this->assertSame([[' First', 0], [' Second', 1], [' Third', 2]], $users);
    }

    public function test_pluck()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $simple = EloquentTestUser::oldest('id')->pluck('users.email')->all();
        $keyed = EloquentTestUser::oldest('id')->pluck('users.email', 'users.id')->all();

        $this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
        $this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
    }

    public function test_pluck_with_join()
    {
        $user1 = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user2 = EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $user2->posts()->create(['id' => 1, 'name' => 'First post']);
        $user1->posts()->create(['id' => 2, 'name' => 'Second post']);

        $query = EloquentTestUser::join('posts', 'users.id', '=', 'posts.user_id');

        $this->assertEquals([1 => 'First post', 2 => 'Second post'], $query->pluck('posts.name', 'posts.id')->all());
        $this->assertEquals([2 => 'First post', 1 => 'Second post'], $query->pluck('posts.name', 'users.id')->all());
        $this->assertEquals(['abigailotwell@gmail.com' => 'First post', 'taylorotwell@gmail.com' => 'Second post'], $query->pluck('posts.name', 'users.email AS user_email')->all());
    }

    public function test_pluck_with_column_name_containing_a_space()
    {
        if ($this->connection('default')->getDriverName() === 'oracle'
            && $this->connection('default')->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward because of too long identifier names.');
        }

        EloquentTestUserWithSpaceInColumnName::insert([
            ['id' => 1, 'email address' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email address' => 'abigailotwell@gmail.com'],
        ]);

        $simple = EloquentTestUserWithSpaceInColumnName::oldest('id')->pluck('users_with_space_in_column_name.email address')->all();
        $keyed = EloquentTestUserWithSpaceInColumnName::oldest('id')->pluck('email address', 'id')->all();

        $this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
        $this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
    }

    public function test_find_or_fail()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $single = EloquentTestUser::findOrFail(1);
        $multiple = EloquentTestUser::findOrFail([1, 2]);

        $this->assertInstanceOf(EloquentTestUser::class, $single);
        $this->assertSame('taylorotwell@gmail.com', $single->email);
        $this->assertInstanceOf(Collection::class, $multiple);
        $this->assertInstanceOf(EloquentTestUser::class, $multiple[0]);
        $this->assertInstanceOf(EloquentTestUser::class, $multiple[1]);
    }

    public function test_find_or_fail_with_single_id_throws_model_not_found_exception()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Illuminate\Tests\Database\EloquentTestUser] 1');
        $this->expectExceptionObject(
            (new ModelNotFoundException)->setModel(EloquentTestUser::class, [1]),
        );

        EloquentTestUser::findOrFail(1);
    }

    public function test_find_or_fail_with_multiple_ids_throws_model_not_found_exception()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Illuminate\Tests\Database\EloquentTestUser] 2, 3');
        $this->expectExceptionObject(
            (new ModelNotFoundException)->setModel(EloquentTestUser::class, [2, 3]),
        );

        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::findOrFail([1, 2, 3]);
    }

    public function test_find_or_fail_with_multiple_ids_using_collection_throws_model_not_found_exception()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [Illuminate\Tests\Database\EloquentTestUser] 2, 3');
        $this->expectExceptionObject(
            (new ModelNotFoundException)->setModel(EloquentTestUser::class, [2, 3]),
        );

        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::findOrFail(new Collection([1, 1, 2, 3]));
    }

    public function test_one_to_one_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $post = $user->post;
        $user = $post->user;

        $this->assertTrue(isset($user->post->name));
        $this->assertInstanceOf(EloquentTestUser::class, $user);
        $this->assertInstanceOf(EloquentTestPost::class, $post);
        $this->assertSame('taylorotwell@gmail.com', $user->email);
        $this->assertSame('First Post', $post->name);
    }

    public function test_isset_loads_in_relationship_if_it_isnt_loaded_already()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $this->assertTrue(isset($user->post->name));
    }

    public function test_one_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user->posts()->create(['name' => 'Second Post']);

        $posts = $user->posts;
        $post2 = $user->posts()->where('name', 'Second Post')->first();

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(EloquentTestPost::class, $posts[0]);
        $this->assertInstanceOf(EloquentTestPost::class, $posts[1]);
        $this->assertInstanceOf(EloquentTestPost::class, $post2);
        $this->assertSame('Second Post', $post2->name);
        $this->assertInstanceOf(EloquentTestUser::class, $post2->user);
        $this->assertSame('taylorotwell@gmail.com', $post2->user->email);
    }

    public function test_basic_model_hydration()
    {
        $user = new EloquentTestUser(['email' => 'taylorotwell@gmail.com']);
        $user->setConnection('second_connection');
        $user->save();

        $user = new EloquentTestUser(['email' => 'abigailotwell@gmail.com']);
        $user->setConnection('second_connection');
        $user->save();

        $models = EloquentTestUser::on('second_connection')->fromQuery('SELECT * FROM users WHERE email = ?', ['abigailotwell@gmail.com']);

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(EloquentTestUser::class, $models[0]);
        $this->assertSame('abigailotwell@gmail.com', $models[0]->email);
        $this->assertSame('second_connection', $models[0]->getConnectionName());
        $this->assertCount(1, $models);
    }

    public function test_first_or_new_on_has_one_relation_ship()
    {
        $user1 = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post1 = $user1->post()->firstOrNew(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('New Post', $post1->name);

        $user2 = EloquentTestUser::create(['email' => 'abigailotwell@gmail.com']);
        $post = $user2->post()->create(['name' => 'First Post']);
        $post2 = $user2->post()->firstOrNew(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('First Post', $post2->name);
        $this->assertSame($post->id, $post2->id);
    }

    public function test_first_or_create_on_has_one_relation_ship()
    {
        $user1 = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post1 = $user1->post()->firstOrCreate(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('New Post', $post1->name);

        $user2 = EloquentTestUser::create(['email' => 'abigailotwell@gmail.com']);
        $post = $user2->post()->create(['name' => 'First Post']);
        $post2 = $user2->post()->firstOrCreate(['name' => 'First Post'], ['name' => 'New Post']);

        $this->assertSame('First Post', $post2->name);
        $this->assertSame($post->id, $post2->id);
    }

    public function test_has_on_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $this->assertTrue(isset($user->friends[0]->id));

        $results = EloquentTestUser::has('friends')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_where_has_on_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::whereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_with_where_has_on_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::withWhereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
    }

    public function test_has_on_nested_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::has('friends.friends')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_where_has_on_nested_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::whereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_with_where_has_on_nested_self_referencing_belongs_to_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::withWhereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
        $this->assertSame($results->first()->friends->pluck('friends')->flatten()->pluck('email')->unique()->toArray(), ['foo@gmail.com']);
    }

    public function test_has_on_self_referencing_belongs_to_many_relationship_with_where_pivot()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::has('friendsOne')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_has_on_nested_self_referencing_belongs_to_many_relationship_with_where_pivot()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::has('friendsOne.friendsTwo')->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
    }

    public function test_has_on_self_referencing_belongs_to_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::has('parentPost')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function test_aggregated_values_of_datetime_field()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'test1@test.test', 'created_at' => '2016-08-10 09:21:00', 'updated_at' => Carbon::now()],
            ['id' => 2, 'email' => 'test2@test.test', 'created_at' => '2016-08-01 12:00:00', 'updated_at' => Carbon::now()],
        ]);

        $this->assertSame('2016-08-10 09:21:00', EloquentTestUser::max('created_at'));
        $this->assertSame('2016-08-01 12:00:00', EloquentTestUser::min('created_at'));
    }

    public function test_where_has_on_self_referencing_belongs_to_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::whereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function test_with_where_has_on_self_referencing_belongs_to_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::withWhereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
    }

    public function test_has_on_nested_self_referencing_belongs_to_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::has('parentPost.parentPost')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function test_where_has_on_nested_self_referencing_belongs_to_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::whereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
    }

    public function test_with_where_has_on_nested_self_referencing_belongs_to_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::withWhereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
        $this->assertTrue($results->first()->parentPost->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->parentPost->name, 'Grandparent Post');
    }

    public function test_has_on_self_referencing_has_many_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::has('childPosts')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
    }

    public function test_where_has_on_self_referencing_has_many_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::whereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
    }

    public function test_with_where_has_on_self_referencing_has_many_relationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::withWhereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    public function test_has_on_nested_self_referencing_has_many_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::has('childPosts.childPosts')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
    }

    public function test_where_has_on_nested_self_referencing_has_many_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::whereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
    }

    public function test_with_where_has_on_nested_self_referencing_has_many_relationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::withWhereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Parent Post']);
        $this->assertSame($results->first()->childPosts->pluck('childPosts')->flatten()->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    public function test_has_with_non_where_bindings()
    {
        $user = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $user->posts()->create(['name' => 'Post 2'])
            ->photos()->create(['name' => 'photo.jpg']);

        $query = EloquentTestUser::has('postWithPhotos');

        $bindingsCount = count($query->getBindings());
        $questionMarksCount = substr_count($query->toSql(), '?');

        $this->assertEquals($questionMarksCount, $bindingsCount);
    }

    public function test_has_on_morph_to_relationship()
    {
        $post = EloquentTestPost::create(['name' => 'Morph Post', 'user_id' => 1]);
        (new EloquentTestPhoto)->imageable()->associate($post)->fill(['name' => 'Morph Photo'])->save();

        $photos = EloquentTestPhoto::has('imageable')->get();

        $this->assertEquals(1, $photos->count());
    }

    public function test_belongs_to_many_relationship_models_are_properly_hydrated_with_sole_query()
    {
        $user = EloquentTestUserWithCustomFriendPivot::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $user->friends()->get()->each(function ($friend) {
            $this->assertInstanceOf(EloquentTestFriendPivot::class, $friend->pivot);
        });

        $soleFriend = $user->friends()->where('email', 'abigailotwell@gmail.com')->sole();

        $this->assertInstanceOf(EloquentTestFriendPivot::class, $soleFriend->pivot);
    }

    public function test_belongs_to_many_relationship_missing_model_exception_with_sole_query_works()
    {
        $this->expectException(ModelNotFoundException::class);
        $user = EloquentTestUserWithCustomFriendPivot::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->where('email', 'abigailotwell@gmail.com')->sole();
    }

    public function test_belongs_to_many_relationship_models_are_properly_hydrated_over_chunked_request()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        EloquentTestUser::first()->friends()->chunk(2, function ($friends) use ($user, $friend) {
            $this->assertCount(1, $friends);
            $this->assertSame('abigailotwell@gmail.com', $friends->first()->email);
            $this->assertEquals($user->id, $friends->first()->pivot->user_id);
            $this->assertEquals($friend->id, $friends->first()->pivot->friend_id);
        });
    }

    public function test_belongs_to_many_relationship_models_are_properly_hydrated_over_each_request()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        EloquentTestUser::first()->friends()->each(function ($result) use ($user, $friend) {
            $this->assertSame('abigailotwell@gmail.com', $result->email);
            $this->assertEquals($user->id, $result->pivot->user_id);
            $this->assertEquals($friend->id, $result->pivot->friend_id);
        });
    }

    public function test_belongs_to_many_relationship_models_are_properly_hydrated_over_cursor_request()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        foreach (EloquentTestUser::first()->friends()->cursor() as $result) {
            $this->assertSame('abigailotwell@gmail.com', $result->email);
            $this->assertEquals($user->id, $result->pivot->user_id);
            $this->assertEquals($friend->id, $result->pivot->friend_id);
        }
    }

    public function test_where_attached_to()
    {
        if ($this->connection('default')->getDriverName() === 'oracle'
            && $this->connection('default')->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward because of too long identifier names.');
        }

        EloquentTestUser::insert([
            ['email' => 'user1@gmail.com'],
            ['email' => 'user2@gmail.com'],
            ['email' => 'user3@gmail.com'],
        ]);

        [$user1, $user2, $user3] = EloquentTestUser::get();

        EloquentTestAchievement::fillAndInsert([['status' => 3], [], []]);
        [$achievement1, $achievement2, $achievement3] = EloquentTestAchievement::get();

        $user1->eloquentTestAchievements()->attach([$achievement1]);
        $user2->eloquentTestAchievements()->attach([$achievement1, $achievement3]);
        $user3->eloquentTestAchievements()->attach([$achievement2, $achievement3]);

        $achievedAchievement1 = EloquentTestUser::whereAttachedTo($achievement1)->get();

        $this->assertSame(2, $achievedAchievement1->count());
        $this->assertTrue($achievedAchievement1->contains($user1));
        $this->assertTrue($achievedAchievement1->contains($user2));

        $achievedByUser1or2 = EloquentTestAchievement::whereAttachedTo(
            new Collection([$user1, $user2])
        )->get();

        $this->assertSame(2, $achievedByUser1or2->count());
        $this->assertTrue($achievedByUser1or2->contains($achievement1));
        $this->assertTrue($achievedByUser1or2->contains($achievement3));
    }

    public function test_basic_has_many_eager_loading()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user = EloquentTestUser::with('posts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertSame('First Post', $user->posts->first()->name);

        $post = EloquentTestPost::with('user')->where('name', 'First Post')->get();
        $this->assertSame('taylorotwell@gmail.com', $post->first()->user->email);
    }

    public function test_basic_nested_self_referencing_has_many_eager_loading()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->childPosts()->create(['name' => 'Child Post', 'user_id' => $user->id]);

        $user = EloquentTestUser::with('posts.childPosts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertNotNull($user->posts->first());
        $this->assertSame('First Post', $user->posts->first()->name);

        $this->assertNotNull($user->posts->first()->childPosts->first());
        $this->assertSame('Child Post', $user->posts->first()->childPosts->first()->name);

        $post = EloquentTestPost::with('parentPost.user')->where('name', 'Child Post')->get();
        $this->assertNotNull($post->first()->parentPost);
        $this->assertNotNull($post->first()->parentPost->user);
        $this->assertSame('taylorotwell@gmail.com', $post->first()->parentPost->user->email);
    }

    public function test_basic_morph_many_relationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf(Collection::class, $user->photos);
        $this->assertInstanceOf(EloquentTestPhoto::class, $user->photos[0]);
        $this->assertInstanceOf(Collection::class, $post->photos);
        $this->assertInstanceOf(EloquentTestPhoto::class, $post->photos[0]);
        $this->assertCount(2, $user->photos);
        $this->assertCount(2, $post->photos);
        $this->assertSame('Avatar 1', $user->photos[0]->name);
        $this->assertSame('Avatar 2', $user->photos[1]->name);
        $this->assertSame('Hero 1', $post->photos[0]->name);
        $this->assertSame('Hero 2', $post->photos[1]->name);

        $photos = EloquentTestPhoto::orderBy('name')->get();

        $this->assertInstanceOf(Collection::class, $photos);
        $this->assertCount(4, $photos);
        $this->assertInstanceOf(EloquentTestUser::class, $photos[0]->imageable);
        $this->assertInstanceOf(EloquentTestPost::class, $photos[2]->imageable);
        $this->assertSame('taylorotwell@gmail.com', $photos[1]->imageable->email);
        $this->assertSame('First Post', $photos[3]->imageable->name);
    }

    public function test_morph_map_is_used_for_creating_and_fetching_through_relation()
    {
        Relation::morphMap([
            'user' => EloquentTestUser::class,
            'post' => EloquentTestPost::class,
        ]);

        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf(Collection::class, $user->photos);
        $this->assertInstanceOf(EloquentTestPhoto::class, $user->photos[0]);
        $this->assertInstanceOf(Collection::class, $post->photos);
        $this->assertInstanceOf(EloquentTestPhoto::class, $post->photos[0]);
        $this->assertCount(2, $user->photos);
        $this->assertCount(2, $post->photos);
        $this->assertSame('Avatar 1', $user->photos[0]->name);
        $this->assertSame('Avatar 2', $user->photos[1]->name);
        $this->assertSame('Hero 1', $post->photos[0]->name);
        $this->assertSame('Hero 2', $post->photos[1]->name);

        $this->assertSame('user', $user->photos[0]->imageable_type);
        $this->assertSame('user', $user->photos[1]->imageable_type);
        $this->assertSame('post', $post->photos[0]->imageable_type);
        $this->assertSame('post', $post->photos[1]->imageable_type);
    }

    public function test_morph_map_is_used_when_fetching_parent()
    {
        Relation::morphMap([
            'user' => EloquentTestUser::class,
            'post' => EloquentTestPost::class,
        ]);

        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);

        $photo = EloquentTestPhoto::first();
        $this->assertSame('user', $photo->imageable_type);
        $this->assertInstanceOf(EloquentTestUser::class, $photo->imageable);
    }

    public function test_morph_map_is_merged_by_default()
    {
        $map1 = [
            'user' => EloquentTestUser::class,
        ];
        $map2 = [
            'post' => EloquentTestPost::class,
        ];

        Relation::morphMap($map1);
        Relation::morphMap($map2);

        $this->assertEquals(array_merge($map1, $map2), Relation::morphMap());
    }

    public function test_morph_map_overwrites_current_map()
    {
        $map1 = [
            'user' => EloquentTestUser::class,
        ];
        $map2 = [
            'post' => EloquentTestPost::class,
        ];

        Relation::morphMap($map1, false);
        $this->assertEquals($map1, Relation::morphMap());
        Relation::morphMap($map2, false);
        $this->assertEquals($map2, Relation::morphMap());
    }

    public function test_empty_morph_to_relationship()
    {
        $photo = new EloquentTestPhoto;

        $this->assertNull($photo->imageable);
    }

    public function test_save_or_fail()
    {
        $date = '2001-01-01 00:00:00';
        $post = new EloquentTestPost([
            'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $this->assertTrue($post->saveOrFail());
        $this->assertEquals(1, EloquentTestPost::count());
    }

    public function test_saving_json_fields()
    {
        $model = EloquentTestWithJSON::create(['json' => ['x' => 0]]);
        $this->assertEquals(['x' => 0], $model->json);

        $model->fillable(['json->y', 'json->a->b']);

        $model->update(['json->y' => '1']);
        $this->assertArrayNotHasKey('json->y', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1], $model->json);

        $model->update(['json->a->b' => '3']);
        $this->assertArrayNotHasKey('json->a->b', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1, 'a' => ['b' => 3]], $model->json);
    }

    public function test_save_or_fail_with_duplicated_entry()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage(
            $this->isPgsql()
                ? 'SQLSTATE[23505]:'
                : ($this->isMariaDb() ? 'SQLSTATE[23000]:' : 'ORA-00001')
        );

        $date = '2001-01-01 00:00:00';
        EloquentTestPost::create([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $post = new EloquentTestPost([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date,
        ]);

        $post->saveOrFail();
    }

    public function test_multi_inserts_with_different_values()
    {
        $date = '2001-01-01 00:00:00';
        $result = EloquentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
            ['user_id' => 2, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, EloquentTestPost::count());
    }

    public function test_multi_inserts_with_same_values()
    {
        $date = '2001-01-01 00:00:00';
        $result = EloquentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
            ['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, EloquentTestPost::count());
    }

    public function test_nested_transactions()
    {
        $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $this->connection()->transaction(function () use ($user) {
                    $user->email = 'otwell@laravel.com';
                    $user->save();
                    throw new Exception;
                });
            } catch (Exception) {
                // ignore the exception
            }
            $user = EloquentTestUser::first();
            $this->assertSame('taylor@laravel.com', $user->email);
        });
    }

    public function test_nested_transactions_using_save_or_fail_will_succeed()
    {
        $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $user->email = 'otwell@laravel.com';
                $user->saveOrFail();
            } catch (Exception) {
                // ignore the exception
            }

            $user = EloquentTestUser::first();
            $this->assertSame('otwell@laravel.com', $user->email);
            $this->assertEquals(1, $user->id);
        });
    }

    public function test_nested_transactions_using_save_or_fail_will_fails()
    {
        $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
        $this->connection()->transaction(function () use ($user) {
            try {
                $user->id = 'invalid';
                $user->email = 'otwell@laravel.com';
                $user->saveOrFail();
            } catch (Exception) {
                // ignore the exception
            }

            $user = EloquentTestUser::first();
            $this->assertSame('taylor@laravel.com', $user->email);
            $this->assertEquals(1, $user->id);
        });
    }

    public function test_to_array_includes_default_formatted_timestamps()
    {
        $model = new EloquentTestUser;

        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => '2012-12-05',
        ]);

        $array = $model->toArray();

        $this->assertSame('2012-12-04T00:00:00.000000Z', $array['created_at']);
        $this->assertSame('2012-12-05T00:00:00.000000Z', $array['updated_at']);
    }

    public function test_to_array_includes_custom_formatted_timestamps()
    {
        $model = new EloquentTestUserWithCustomDateSerialization;

        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => '2012-12-05',
        ]);

        $array = $model->toArray();

        $this->assertSame('04-12-12', $array['created_at']);
        $this->assertSame('05-12-12', $array['updated_at']);
    }

    public function test_incrementing_primary_keys_are_cast_to_integers_by_default()
    {
        EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user = EloquentTestUser::first();
        $this->assertIsInt($user->id);
    }

    public function test_default_incrementing_primary_key_integer_cast_can_be_overwritten()
    {
        EloquentTestUserWithStringCastId::create(['email' => 'taylorotwell@gmail.com']);

        $user = EloquentTestUserWithStringCastId::first();
        $this->assertIsString($user->id);
    }

    public function test_relations_are_preloaded_in_global_scope()
    {
        $user = EloquentTestUserWithGlobalScope::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'My Post']);

        $result = EloquentTestUserWithGlobalScope::first();

        $this->assertCount(1, $result->getRelations());
    }

    public function test_model_ignored_by_global_scope_can_be_refreshed()
    {
        $user = EloquentTestUserWithOmittingGlobalScope::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $this->assertNotNull($user->fresh());
    }

    public function test_global_scope_can_be_removed_by_other_global_scope()
    {
        $user = EloquentTestUserWithGlobalScopeRemovingOtherScope::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user->delete();

        $this->assertNotNull(EloquentTestUserWithGlobalScopeRemovingOtherScope::find($user->id));
    }

    public function test_for_page_before_id_correctly_paginates()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $results = EloquentTestUser::forPageBeforeId(15, 2);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(1, $results->first()->id);

        $results = EloquentTestUser::orderBy('id', 'desc')->forPageBeforeId(15, 2);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(1, $results->first()->id);
    }

    public function test_for_page_after_id_correctly_paginates()
    {
        EloquentTestUser::insert([
            ['id' => 1, 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'email' => 'abigailotwell@gmail.com'],
        ]);

        $results = EloquentTestUser::forPageAfterId(15, 1);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(2, $results->first()->id);

        $results = EloquentTestUser::orderBy('id', 'desc')->forPageAfterId(15, 1);
        $this->assertInstanceOf(Builder::class, $results);
        $this->assertEquals(2, $results->first()->id);
    }

    public function test_morph_to_relations_across_database_connections()
    {
        $item = null;

        EloquentTestItem::create(['id' => 1]);
        EloquentTestOrder::create(['id' => 1, 'item_type' => EloquentTestItem::class, 'item_id' => 1]);
        try {
            $item = EloquentTestOrder::first()->item;
        } catch (Exception) {
            // ignore the exception
        }

        $this->assertInstanceOf(EloquentTestItem::class, $item);
    }

    public function test_eager_loaded_morph_to_relations_on_another_database_connection()
    {
        EloquentTestPost::create(['id' => 1, 'name' => 'Default Connection Post', 'user_id' => 1]);
        EloquentTestPhoto::create(['id' => 1, 'imageable_type' => EloquentTestPost::class, 'imageable_id' => 1, 'name' => 'Photo']);

        EloquentTestPost::on('second_connection')
            ->create(['id' => 1, 'name' => 'Second Connection Post', 'user_id' => 1]);
        EloquentTestPhoto::on('second_connection')
            ->create(['id' => 1, 'imageable_type' => EloquentTestPost::class, 'imageable_id' => 1, 'name' => 'Photo']);

        $defaultConnectionPost = EloquentTestPhoto::with('imageable')->first()->imageable;
        $secondConnectionPost = EloquentTestPhoto::on('second_connection')->with('imageable')->first()->imageable;

        $this->assertSame('Default Connection Post', $defaultConnectionPost->name);
        $this->assertSame('Second Connection Post', $secondConnectionPost->name);
    }

    public function test_belongs_to_many_custom_pivot()
    {
        $john = EloquentTestUserWithCustomFriendPivot::create(['id' => 1, 'name' => 'John Doe', 'email' => 'johndoe@example.com']);
        $jane = EloquentTestUserWithCustomFriendPivot::create(['id' => 2, 'name' => 'Jane Doe', 'email' => 'janedoe@example.com']);
        $jack = EloquentTestUserWithCustomFriendPivot::create(['id' => 3, 'name' => 'Jack Doe', 'email' => 'jackdoe@example.com']);
        $jule = EloquentTestUserWithCustomFriendPivot::create(['id' => 4, 'name' => 'Jule Doe', 'email' => 'juledoe@example.com']);

        EloquentTestFriendLevel::insert([
            ['id' => 1, 'level' => 'acquaintance'],
            ['id' => 2, 'level' => 'friend'],
            ['id' => 3, 'level' => 'bff'],
        ]);

        $john->friends()->attach($jane, ['friend_level_id' => 1]);
        $john->friends()->attach($jack, ['friend_level_id' => 2]);
        $john->friends()->attach($jule, ['friend_level_id' => 3]);

        $johnWithFriends = EloquentTestUserWithCustomFriendPivot::with('friends')->find(1);

        $this->assertCount(3, $johnWithFriends->friends);
        $this->assertSame('friend', $johnWithFriends->friends->find(3)->pivot->level->level);
        $this->assertSame('Jule Doe', $johnWithFriends->friends->find(4)->pivot->friend->name);
    }

    public function test_is_after_retrieving_the_same_model()
    {
        $saved = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $retrieved = EloquentTestUser::find(1);

        $this->assertTrue($saved->is($retrieved));
    }

    public function test_fresh_method_on_model()
    {
        $now = Carbon::now()->startOfSecond();
        $nowSerialized = $now->toJSON();
        $nowWithFractionsSerialized = $now->toJSON();
        Carbon::setTestNow($now);

        $storedUser1 = EloquentTestUser::create([
            'id' => 1,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $storedUser1->newQuery()->update([
            'email' => 'dev@mathieutu.ovh',
            'name' => 'Mathieu TUDISCO',
        ]);
        $freshStoredUser1 = $storedUser1->fresh();

        $storedUser2 = EloquentTestUser::create([
            'id' => 2,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $storedUser2->newQuery()->update(['email' => 'dev@mathieutu.ovh']);
        $freshStoredUser2 = $storedUser2->fresh();

        $notStoredUser = new EloquentTestUser([
            'id' => 3,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $now,
        ]);
        $freshNotStoredUser = $notStoredUser->fresh();

        $this->assertEquals([
            'id' => 1,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $storedUser1->toArray());
        $this->assertEquals([
            'id' => 1,
            'name' => 'Mathieu TUDISCO',
            'email' => 'dev@mathieutu.ovh',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $freshStoredUser1->toArray());
        $this->assertInstanceOf(EloquentTestUser::class, $storedUser1);

        $this->assertEquals([
            'id' => 2,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $storedUser2->toArray());
        $this->assertEquals([
            'id' => 2,
            'name' => null,
            'email' => 'dev@mathieutu.ovh',
            'birthday' => $nowWithFractionsSerialized,
            'created_at' => $nowSerialized,
            'updated_at' => $nowSerialized,
        ], $freshStoredUser2->toArray());
        $this->assertInstanceOf(EloquentTestUser::class, $storedUser2);

        $this->assertEquals([
            'id' => 3,
            'email' => 'taylorotwell@gmail.com',
            'birthday' => $nowWithFractionsSerialized,
        ], $notStoredUser->toArray());
        $this->assertNull($freshNotStoredUser);
    }

    public function test_fresh_method_on_collection()
    {
        EloquentTestUser::insert([['id' => 1, 'email' => 'taylorotwell@gmail.com'], ['id' => 2, 'email' => 'taylorotwell@gmail.com']]);

        $users = EloquentTestUser::all()
            ->add(new EloquentTestUser(['id' => 3, 'email' => 'taylorotwell@gmail.com']));

        EloquentTestUser::find(1)->update(['name' => 'Mathieu TUDISCO']);
        EloquentTestUser::find(2)->update(['email' => 'dev@mathieutu.ovh']);

        $this->assertCount(3, $users);
        $this->assertNotSame('Mathieu TUDISCO', $users[0]->name);
        $this->assertNotSame('dev@mathieutu.ovh', $users[1]->email);

        $refreshedUsers = $users->fresh();

        $this->assertCount(2, $refreshedUsers);
        $this->assertSame('Mathieu TUDISCO', $refreshedUsers[0]->name);
        $this->assertSame('dev@mathieutu.ovh', $refreshedUsers[1]->email);
    }

    public function test_timestamps_using_default_date_format()
    {
        $model = new EloquentTestUser;
        $model->setDateFormat('Y-m-d H:i:s'); // Default MySQL/PostgreSQL/SQLite date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19',
        ]);

        $this->assertSame('2017-11-14 08:23:19', $model->fromDateTime($model->getAttribute('created_at')));
    }

    public function test_timestamps_using_default_sql_server_date_format()
    {
        $model = new EloquentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.v'); // Default SQL Server date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.000',
            'updated_at' => '2017-11-14 08:23:19.734',
        ]);

        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($model->getAttribute('created_at')));
        $this->assertSame('2017-11-14 08:23:19.734', $model->fromDateTime($model->getAttribute('updated_at')));
    }

    public function test_timestamps_using_custom_date_format()
    {
        // Simulating using custom precisions with timestamps(4)
        $model = new EloquentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.u'); // Custom date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.0000',
            'updated_at' => '2017-11-14 08:23:19.7348',
        ]);

        // Note: when storing databases would truncate the value to the given precision
        $this->assertSame('2017-11-14 08:23:19.000000', $model->fromDateTime($model->getAttribute('created_at')));
        $this->assertSame('2017-11-14 08:23:19.734800', $model->fromDateTime($model->getAttribute('updated_at')));
    }

    public function test_timestamps_using_old_sql_server_date_format()
    {
        $model = new EloquentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.000'); // Old SQL Server date format
        $model->setRawAttributes([
            'created_at' => '2017-11-14 08:23:19.000',
        ]);

        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($model->getAttribute('created_at')));
    }

    public function test_timestamps_using_old_sql_server_date_format_fallback_to_default_parsing()
    {
        $model = new EloquentTestUser;
        $model->setDateFormat('Y-m-d H:i:s.000'); // Old SQL Server date format
        $model->setRawAttributes([
            'updated_at' => '2017-11-14 08:23:19.734',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2017-11-14 08:23:19.734', $date->format('Y-m-d H:i:s.v'), 'the date should contains the precision');
        $this->assertSame('2017-11-14 08:23:19.000', $model->fromDateTime($date), 'the format should trims it');
        // No longer throwing exception since Laravel 7,
        // but Date::hasFormat() can be used instead to check date formatting:
        $this->assertTrue(Date::hasFormat('2017-11-14 08:23:19.000', $model->getDateFormat()));
        $this->assertFalse(Date::hasFormat('2017-11-14 08:23:19.734', $model->getDateFormat()));
    }

    public function test_special_formats()
    {
        $model = new EloquentTestUser;
        $model->setDateFormat('!Y-d-m \\Y');
        $model->setRawAttributes([
            'updated_at' => '2017-05-11 Y',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2017-11-05 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');

        $model->setDateFormat('Y d m|');
        $model->setRawAttributes([
            'updated_at' => '2020 11 09',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2020-09-11 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');

        $model->setDateFormat('Y d m|*');
        $model->setRawAttributes([
            'updated_at' => '2020 11 09 foo',
        ]);

        $date = $model->getAttribute('updated_at');
        $this->assertSame('2020-09-11 00:00:00.000000', $date->format('Y-m-d H:i:s.u'), 'the date should respect the whole format');
    }

    public function test_updating_child_model_touches_parent()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->update(['name' => 'Updated']);

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function test_multi_level_touching_works()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching models related timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function test_deleting_child_model_touches_parent_timestamps()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->delete();

        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function test_touching_child_model_updates_parents_timestamps()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $post->touch();

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is not touching models related timestamps.');
    }

    public function test_touching_child_model_respects_parent_no_touching()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingUser::withoutTouching(function () use ($post) {
            $post->touch();
        });

        $this->assertTrue(
            $future->isSameDay($post->fresh()->updated_at),
            'It is not touching model own timestamps in withoutTouching scope.'
        );

        $this->assertTrue(
            $before->isSameDay($user->fresh()->updated_at),
            'It is touching model own timestamps in withoutTouching scope, when it should not.'
        );
    }

    public function test_updating_child_post_respects_no_touching_definition()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingUser::withoutTouching(function () use ($post) {
            $post->update(['name' => 'Updated']);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is not touching model own timestamps when it should.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models relationships when it should be disabled.');
    }

    public function test_updating_model_in_the_disabled_scope_touches_its_own_timestamps()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        Model::withoutTouching(function () use ($post) {
            $post->update(['name' => 'Updated']);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_deleting_child_model_respects_the_no_touching_rule()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingUser::withoutTouching(function () use ($post) {
            $post->delete();
        });

        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_respected_multi_level_touching_chain()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingUser::withoutTouching(function () {
            EloquentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($future->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_touches_great_parent_even_when_parent_is_in_no_touch_scope()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingPost::withoutTouching(function () {
            EloquentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($future->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_can_nest_calls_of_no_touching()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        EloquentTouchingUser::withoutTouching(function () {
            EloquentTouchingPost::withoutTouching(function () {
                EloquentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
            });
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_can_pass_array_of_models_to_ignore()
    {
        $before = Carbon::now();

        $user = EloquentTouchingUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post = EloquentTouchingPost::create(['id' => 1, 'name' => 'Parent Post', 'user_id' => 1]);

        $this->assertTrue($before->isSameDay($user->updated_at));
        $this->assertTrue($before->isSameDay($post->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        Model::withoutTouchingOn([EloquentTouchingUser::class, EloquentTouchingPost::class], function () {
            EloquentTouchingComment::create(['content' => 'Comment content', 'post_id' => 1]);
        });

        $this->assertTrue($before->isSameDay($post->fresh()->updated_at), 'It is touching models when it should be disabled.');
        $this->assertTrue($before->isSameDay($user->fresh()->updated_at), 'It is touching models when it should be disabled.');
    }

    public function test_when_base_model_is_ignored_all_child_models_are_ignored()
    {
        $this->assertFalse(Model::isIgnoringTouch());
        $this->assertFalse(User::isIgnoringTouch());

        Model::withoutTouching(function () {
            $this->assertTrue(Model::isIgnoringTouch());
            $this->assertTrue(User::isIgnoringTouch());
        });

        $this->assertFalse(User::isIgnoringTouch());
        $this->assertFalse(Model::isIgnoringTouch());
    }

    public function test_child_models_are_ignored()
    {
        $this->assertFalse(Model::isIgnoringTouch());
        $this->assertFalse(User::isIgnoringTouch());
        $this->assertFalse(Post::isIgnoringTouch());

        User::withoutTouching(function () {
            $this->assertFalse(Model::isIgnoringTouch());
            $this->assertFalse(Post::isIgnoringTouch());
            $this->assertTrue(User::isIgnoringTouch());
        });

        $this->assertFalse(Post::isIgnoringTouch());
        $this->assertFalse(User::isIgnoringTouch());
        $this->assertFalse(Model::isIgnoringTouch());
    }

    public function test_pivots_can_be_refreshed()
    {
        EloquentTestFriendLevel::create(['id' => 1, 'level' => 'acquaintance']);
        EloquentTestFriendLevel::create(['id' => 2, 'level' => 'friend']);

        $user = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['id' => 2, 'email' => 'abigailotwell@gmail.com'], ['friend_level_id' => 1]);

        $pivot = $user->friends[0]->pivot;

        // Simulate a change that happened externally
        DB::table('friends')->where('user_id', 1)->where('friend_id', 2)->update([
            'friend_level_id' => 2,
        ]);

        $this->assertInstanceOf(Pivot::class, $freshPivot = $pivot->fresh());
        $this->assertEquals(2, $freshPivot->friend_level_id);

        $this->assertSame($pivot, $pivot->refresh());
        $this->assertEquals(2, $pivot->friend_level_id);
    }

    public function test_morph_pivots_can_be_refreshed()
    {
        $post = EloquentTestPost::create(['name' => 'MorphToMany Post', 'user_id' => 1]);
        $post->tags()->create(['id' => 1, 'name' => 'News']);

        $pivot = $post->tags[0]->pivot;

        // Simulate a change that happened externally
        DB::table('taggables')
            ->where([
                'taggable_type' => EloquentTestPost::class,
                'taggable_id' => 1,
                'tag_id' => 1,
            ])
            ->update([
                'taxonomy' => 'primary',
            ]);

        $this->assertInstanceOf(MorphPivot::class, $freshPivot = $pivot->fresh());
        $this->assertSame('primary', $freshPivot->taxonomy);

        $this->assertSame($pivot, $pivot->refresh());
        $this->assertSame('primary', $pivot->taxonomy);
    }

    public function test_touching_chaperoned_child_model_updates_parent_timestamps()
    {
        $before = Carbon::now();

        $one = EloquentTouchingCategory::create(['id' => 1, 'name' => 'One']);
        $two = $one->children()->create(['id' => 2, 'name' => 'Two']);

        $this->assertTrue($before->isSameDay($one->updated_at));
        $this->assertTrue($before->isSameDay($two->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        $two->touch();

        $this->assertTrue($future->isSameDay($two->fresh()->updated_at), 'It is not touching model own timestamps.');
        $this->assertTrue($future->isSameDay($one->fresh()->updated_at), 'It is not touching chaperoned models related timestamps.');
    }

    public function test_touching_bi_directional_chaperoned_model_updates_all_related_timestamps()
    {
        $before = Carbon::now();

        EloquentTouchingCategory::insert([
            ['id' => 1, 'name' => 'One', 'parent_id' => null, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 2, 'name' => 'Two', 'parent_id' => 1, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 3, 'name' => 'Three', 'parent_id' => 1, 'created_at' => $before, 'updated_at' => $before],
            ['id' => 4, 'name' => 'Four', 'parent_id' => 2, 'created_at' => $before, 'updated_at' => $before],
        ]);

        $one = EloquentTouchingCategory::find(1);
        [$two, $three] = $one->children;
        [$four] = $two->children;

        $this->assertTrue($before->isSameDay($one->updated_at));
        $this->assertTrue($before->isSameDay($two->updated_at));
        $this->assertTrue($before->isSameDay($three->updated_at));
        $this->assertTrue($before->isSameDay($four->updated_at));

        Carbon::setTestNow($future = $before->copy()->addDays(3));

        // Touch a random model and check that all of the others have been updated
        $models = tap([$one, $two, $three, $four], shuffle(...));
        $target = array_shift($models);
        $target->touch();

        $this->assertTrue($future->isSameDay($target->fresh()->updated_at), 'It is not touching model own timestamps.');

        while ($next = array_shift($models)) {
            $this->assertTrue(
                $future->isSameDay($next->fresh()->updated_at),
                'It is not touching related models timestamps.'
            );
        }
    }

    public function test_can_fill_and_insert()
    {
        DB::enableQueryLog();
        Carbon::setTestNow('2025-03-15T07:32:00Z');

        $this->assertTrue(EloquentTestUser::fillAndInsert([
            ['email' => 'taylor@laravel.com', 'birthday' => null],
            ['email' => 'nuno@laravel.com', 'birthday' => new Carbon('1980-01-01')],
            ['email' => 'tim@laravel.com', 'birthday' => '1987-11-01', 'created_at' => '2025-01-02T02:00:55', 'updated_at' => Carbon::parse('2025-02-19T11:41:13')],
        ]));

        $this->assertCount(1, DB::getQueryLog());

        $this->assertCount(3, $users = EloquentTestUser::get());

        $users->take(2)->each(function (EloquentTestUser $user) {
            $this->assertEquals(Carbon::parse('2025-03-15T07:32:00Z'), $user->created_at);
            $this->assertEquals(Carbon::parse('2025-03-15T07:32:00Z'), $user->updated_at);
        });

        $tim = $users->firstWhere('email', 'tim@laravel.com');
        $this->assertEquals(Carbon::parse('2025-01-02T02:00:55'), $tim->created_at);
        $this->assertEquals(Carbon::parse('2025-02-19T11:41:13'), $tim->updated_at);

        $this->assertNull($users[0]->birthday);
        $this->assertInstanceOf(\DateTime::class, $users[1]->birthday);
        $this->assertInstanceOf(\DateTime::class, $users[2]->birthday);
        $this->assertEquals('1987-11-01', $users[2]->birthday->format('Y-m-d'));

        DB::flushQueryLog();

        $this->assertTrue(EloquentTestWithJSON::fillAndInsert([
            ['id' => 1, 'json' => ['album' => 'Keep It Like a Secret', 'release_date' => '1999-02-02']],
            ['id' => 2, 'json' => (object) ['album' => 'You In Reverse', 'release_date' => '2006-04-11']],
        ]));

        $this->assertCount(1, DB::getQueryLog());

        $this->assertCount(2, $testsWithJson = EloquentTestWithJSON::get());

        $testsWithJson->each(function (EloquentTestWithJSON $testWithJson) {
            $this->assertIsArray($testWithJson->json);
            $this->assertArrayHasKey('album', $testWithJson->json);
        });
    }

    public function test_can_fill_and_insert_with_unique_string_ids()
    {
        Str::createUuidsUsingSequence([
            '00000000-0000-7000-0000-000000000000',
            '11111111-0000-7000-0000-000000000000',
            '22222222-0000-7000-0000-000000000000',
        ]);

        $this->assertTrue(ModelWithUniqueStringIds::fillAndInsert([
            [
                'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
            ],
            [
                'name' => 'Nuno', 'role' => 3, 'role_string' => 'admin',
            ],
            [
                'name' => 'Dries', 'uuid' => 'bbbb0000-0000-7000-0000-000000000000',
            ],
            [
                'name' => 'Chris',
            ],
        ]));

        $models = ModelWithUniqueStringIds::get();

        $taylor = $models->firstWhere('name', 'Taylor');
        $nuno = $models->firstWhere('name', 'Nuno');
        $dries = $models->firstWhere('name', 'Dries');
        $chris = $models->firstWhere('name', 'Chris');

        $this->assertEquals(IntBackedRole::Admin, $taylor->role);
        $this->assertEquals(StringBackedRole::Admin, $taylor->role_string);
        $this->assertSame('00000000-0000-7000-0000-000000000000', $taylor->uuid);

        $this->assertEquals(IntBackedRole::Admin, $nuno->role);
        $this->assertEquals(StringBackedRole::Admin, $nuno->role_string);
        $this->assertSame('11111111-0000-7000-0000-000000000000', $nuno->uuid);

        $this->assertEquals(IntBackedRole::User, $dries->role);
        $this->assertEquals(StringBackedRole::User, $dries->role_string);
        $this->assertSame('bbbb0000-0000-7000-0000-000000000000', $dries->uuid);

        $this->assertEquals(IntBackedRole::User, $chris->role);
        $this->assertEquals(StringBackedRole::User, $chris->role_string);
        $this->assertSame('22222222-0000-7000-0000-000000000000', $chris->uuid);
    }

    // todo: fix test
    //    public function test_fill_and_insert_or_ignore()
    //    {
    //        Str::createUuidsUsingSequence([
    //            '00000000-0000-7000-0000-000000000000',
    //            '11111111-0000-7000-0000-000000000000',
    //            '22222222-0000-7000-0000-000000000000',
    //        ]);
    //
    //        $this->assertEquals(1, ModelWithUniqueStringIds::fillAndInsertOrIgnore([
    //            [
    //                'id' => 1, 'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
    //            ],
    //        ]));
    //
    //        $this->assertSame(1, ModelWithUniqueStringIds::fillAndInsertOrIgnore([
    //            [
    //                'id' => 1, 'name' => 'Taylor', 'role' => IntBackedRole::Admin, 'role_string' => StringBackedRole::Admin,
    //            ],
    //            [
    //                'id' => 2, 'name' => 'Nuno',
    //            ],
    //        ]));
    //
    //        $models = ModelWithUniqueStringIds::get();
    //        $this->assertSame('00000000-0000-7000-0000-000000000000', $models->firstWhere('name', 'Taylor')->uuid);
    //        $this->assertSame(
    //            ['uuid' => '22222222-0000-7000-0000-000000000000', 'role' => IntBackedRole::User],
    //            $models->firstWhere('name', 'Nuno')->only('uuid', 'role')
    //        );
    //    }

    public function test_fill_and_insert_get_id()
    {
        Str::createUuidsUsingSequence([
            '00000000-0000-7000-0000-000000000000',
        ]);

        DB::enableQueryLog();

        $this->assertIsInt($newId = ModelWithUniqueStringIds::fillAndInsertGetId([
            'name' => 'Taylor',
            'role' => IntBackedRole::Admin,
            'role_string' => StringBackedRole::Admin,
        ]));
        $this->assertCount(1, DB::getRawQueryLog());
        $this->assertSame($newId, ModelWithUniqueStringIds::sole()->id);
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
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class EloquentTestUser extends Eloquent
{
    protected $table = 'users';

    protected $casts = ['birthday' => 'datetime'];

    protected $guarded = [];

    public function friends()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id');
    }

    public function friendsOne()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 1);
    }

    public function friendsTwo()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 2);
    }

    public function posts()
    {
        return $this->hasMany(EloquentTestPost::class, 'user_id');
    }

    public function post()
    {
        return $this->hasOne(EloquentTestPost::class, 'user_id');
    }

    public function photos()
    {
        return $this->morphMany(EloquentTestPhoto::class, 'imageable');
    }

    public function postWithPhotos()
    {
        return $this->post()->join('photo', function ($join) {
            $join->on('photo.imageable_id', 'post.id');
            $join->where('photo.imageable_type', 'EloquentTestPost');
        });
    }

    public function eloquentTestAchievements()
    {
        return $this->belongsToMany(EloquentTestAchievement::class);
    }
}

class EloquentTestUserWithCustomFriendPivot extends EloquentTestUser
{
    public function friends()
    {
        return $this->belongsToMany(EloquentTestUser::class, 'friends', 'user_id', 'friend_id')
            ->using(EloquentTestFriendPivot::class)->withPivot('user_id', 'friend_id', 'friend_level_id');
    }
}

class EloquentTestUserWithSpaceInColumnName extends EloquentTestUser
{
    protected $table = 'users_with_space_in_column_name';
}

class EloquentTestNonIncrementing extends Eloquent
{
    protected $table = 'non_incrementing_users';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;
}

class EloquentTestNonIncrementingSecond extends EloquentTestNonIncrementing
{
    protected $connection = 'second_connection';
}

class EloquentTestUserWithGlobalScope extends EloquentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->with('posts');
        });
    }
}

class EloquentTestUserWithOmittingGlobalScope extends EloquentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->where('email', '!=', 'taylorotwell@gmail.com');
        });
    }
}

class EloquentTestUserWithGlobalScopeRemovingOtherScope extends Eloquent
{
    use SoftDeletes;

    protected $table = 'soft_deleted_users';

    protected $guarded = [];

    public static function boot()
    {
        static::addGlobalScope(function ($builder) {
            $builder->withoutGlobalScope(SoftDeletingScope::class);
        });

        parent::boot();
    }
}

class EloquentTestUniqueUser extends Eloquent
{
    protected $table = 'unique_users';

    protected $casts = ['birthday' => 'datetime'];

    protected $guarded = [];
}

class EloquentTestPost extends Eloquent
{
    protected $table = 'posts';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(EloquentTestUser::class, 'user_id');
    }

    public function photos()
    {
        return $this->morphMany(EloquentTestPhoto::class, 'imageable');
    }

    public function childPosts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parentPost()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function tags()
    {
        return $this->morphToMany(EloquentTestTag::class, 'taggable', null, null, 'tag_id')->withPivot('taxonomy');
    }
}

class EloquentTestTag extends Eloquent
{
    protected $table = 'tags';

    protected $guarded = [];
}

class EloquentTestFriendLevel extends Eloquent
{
    protected $table = 'friend_levels';

    protected $guarded = [];
}

class EloquentTestPhoto extends Eloquent
{
    protected $table = 'photos';

    protected $guarded = [];

    public function imageable()
    {
        return $this->morphTo();
    }
}

class EloquentTestUserWithStringCastId extends EloquentTestUser
{
    protected $casts = [
        'id' => 'string',
    ];
}

class EloquentTestUserWithCustomDateSerialization extends EloquentTestUser
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('d-m-y');
    }
}

class EloquentTestOrder extends Eloquent
{
    protected $guarded = [];

    protected $table = 'test_orders';

    protected $with = ['item'];

    public function item()
    {
        return $this->morphTo();
    }
}

class EloquentTestItem extends Eloquent
{
    protected $guarded = [];

    protected $table = 'test_items';

    protected $connection = 'second_connection';
}

class EloquentTestWithJSON extends Eloquent
{
    protected $guarded = [];

    protected $table = 'with_json';

    public $timestamps = false;

    protected $casts = [
        'json' => 'array',
    ];
}

class EloquentTestFriendPivot extends Pivot
{
    protected $table = 'friends';

    protected $guarded = [];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(EloquentTestUser::class);
    }

    public function friend()
    {
        return $this->belongsTo(EloquentTestUser::class);
    }

    public function level()
    {
        return $this->belongsTo(EloquentTestFriendLevel::class, 'friend_level_id');
    }
}

class EloquentTouchingUser extends Eloquent
{
    protected $table = 'users';

    protected $guarded = [];
}

class EloquentTouchingPost extends Eloquent
{
    protected $table = 'posts';

    protected $guarded = [];

    protected $touches = [
        'user',
    ];

    public function user()
    {
        return $this->belongsTo(EloquentTouchingUser::class, 'user_id');
    }
}

class EloquentTouchingComment extends Eloquent
{
    protected $table = 'comments';

    protected $guarded = [];

    protected $touches = [
        'post',
    ];

    public function post()
    {
        return $this->belongsTo(EloquentTouchingPost::class, 'post_id');
    }
}

class EloquentTouchingCategory extends Eloquent
{
    protected $table = 'categories';

    protected $guarded = [];

    protected $touches = [
        'parent',
        'children',
    ];

    public function parent()
    {
        return $this->belongsTo(EloquentTouchingCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(EloquentTouchingCategory::class, 'parent_id')->chaperone();
    }
}

class EloquentTestAchievement extends Eloquent
{
    public $timestamps = false;

    protected $table = 'achievements';

    protected $guarded = [];

    protected $attributes = ['status' => null];

    public function eloquentTestUsers()
    {
        return $this->belongsToMany(EloquentTestUser::class);
    }
}

class ModelWithUniqueStringIds extends Eloquent
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'users_having_uuids';

    protected function casts()
    {
        return [
            'role' => IntBackedRole::class,
            'role_string' => StringBackedRole::class,
        ];
    }

    protected $attributes = [
        'role' => IntBackedRole::User,
        'role_string' => StringBackedRole::User,
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }
}

enum IntBackedRole: int
{
    case User = 1;
    case Admin = 3;
}

enum StringBackedRole: string
{
    case User = 'user';
    case Admin = 'admin';
}
