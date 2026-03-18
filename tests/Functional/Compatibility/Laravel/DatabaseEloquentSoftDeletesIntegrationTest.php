<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use BadMethodCallException;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Mockery as m;
use Mockery\MockInterface;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentSoftDeletesIntegrationTest extends LaravelTestCase
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
            $table->integer('user_id')->nullable(); // circular reference to parent User
            $table->integer('group_id')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('comments', function ($table) {
            $table->increments('id');
            $table->integer('owner_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->integer('post_id');
            $table->string('body');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('addresses', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('address');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('groups', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Tear down the database schema.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        $this->schema()->drop('users');
        $this->schema()->drop('posts');
        $this->schema()->drop('comments');
        $this->schema()->drop('addresses');
        $this->schema()->drop('groups');

        parent::tearDown();
    }

    /**
     * Tests...
     */
    public function test_soft_deletes_are_not_retrieved()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(1));
    }

    public function test_soft_deletes_are_not_retrieved_from_base_query()
    {
        $this->createUsers();

        $query = SoftDeletesTestUser::query()->toBase();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->get());
    }

    public function test_soft_deletes_are_not_retrieved_from_relationship_base_query()
    {
        [, $abigail] = $this->createUsers();

        $abigail->posts()->create(['title' => 'Foo']);
        $abigail->posts()->create(['title' => 'Bar'])->delete();

        $query = $abigail->posts()->toBase();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->get());
    }

    public function test_soft_deletes_are_not_retrieved_from_builder_helpers()
    {
        $this->createUsers();

        $count = 0;
        $query = SoftDeletesTestUser::query();
        $query->chunk(2, function ($user) use (&$count) {
            $count += count($user);
        });
        $this->assertEquals(1, $count);

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->pluck('email')->all());

        Paginator::currentPageResolver(function () {
            return 1;
        });

        CursorPaginator::currentCursorResolver(function () {
            return null;
        });

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->paginate(2)->all());

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->simplePaginate(2)->all());

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->cursorPaginate(2)->all());

        $this->assertEquals(0, SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->increment('id'));
        $this->assertEquals(0, SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->decrement('id'));
    }

    public function test_with_trashed_returns_all_records()
    {
        $this->createUsers();

        $this->assertCount(2, SoftDeletesTestUser::withTrashed()->get());
        $this->assertInstanceOf(Eloquent::class, SoftDeletesTestUser::withTrashed()->find(1));
    }

    public function test_with_trashed_accepts_an_argument()
    {
        $this->createUsers();

        $this->assertCount(1, SoftDeletesTestUser::withTrashed(false)->get());
        $this->assertCount(2, SoftDeletesTestUser::withTrashed(true)->get());
    }

    public function test_delete_sets_deleted_column()
    {
        $this->createUsers();

        $this->assertInstanceOf(Carbon::class, SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
        $this->assertNull(SoftDeletesTestUser::find(2)->deleted_at);
    }

    public function test_force_delete_actually_deletes_records()
    {
        $this->createUsers();
        SoftDeletesTestUser::find(2)->forceDelete();

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function test_force_delete_update_exists_property()
    {
        $this->createUsers();
        $user = SoftDeletesTestUser::find(2);

        $this->assertTrue($user->exists);

        $user->forceDelete();

        $this->assertFalse($user->exists);
    }

    public function test_force_delete_doesnt_update_exists_property_if_failed()
    {
        $user = new class extends SoftDeletesTestUser
        {
            public $exists = true;

            public function newModelQuery()
            {
                return m::spy(parent::newModelQuery(), function (MockInterface $mock) {
                    $mock->shouldReceive('forceDelete')->andThrow(new Exception);
                });
            }
        };

        $this->assertTrue($user->exists);

        try {
            $user->forceDelete();
        } catch (Exception) {
        }

        $this->assertTrue($user->exists);
    }

    public function test_force_destroy_fully_deletes_record()
    {
        $this->createUsers();
        $deleted = SoftDeletesTestUser::forceDestroy(2);

        $this->assertSame(1, $deleted);

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(2));
    }

    public function test_force_destroy_deletes_already_deleted_record()
    {
        $this->createUsers();
        $deleted = SoftDeletesTestUser::forceDestroy(1);

        $this->assertSame(1, $deleted);

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(1));
    }

    public function test_force_destroy_deletes_multiple_records()
    {
        $this->createUsers();
        $deleted = SoftDeletesTestUser::forceDestroy([1, 2]);

        $this->assertSame(2, $deleted);

        $this->assertTrue(SoftDeletesTestUser::withTrashed()->get()->isEmpty());
    }

    public function test_force_destroy_deletes_records_from_collection()
    {
        $this->createUsers();
        $deleted = SoftDeletesTestUser::forceDestroy(collect([1, 2]));

        $this->assertSame(2, $deleted);

        $this->assertTrue(SoftDeletesTestUser::withTrashed()->get()->isEmpty());
    }

    public function test_force_destroy_deletes_records_from_eloquent_collection()
    {
        $this->createUsers();
        $deleted = SoftDeletesTestUser::forceDestroy(SoftDeletesTestUser::all());

        $this->assertSame(1, $deleted);

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(2));
    }

    public function test_restore_restores_records()
    {
        $this->createUsers();
        $taylor = SoftDeletesTestUser::withTrashed()->find(1);

        $this->assertTrue($taylor->trashed());

        $taylor->restore();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(2, $users);
        $this->assertNull($users->find(1)->deleted_at);
        $this->assertNull($users->find(2)->deleted_at);
    }

    public function test_only_trashed_only_returns_trashed_records()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::onlyTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function test_only_without_trashed_only_returns_trashed_records()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::withoutTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);

        $users = SoftDeletesTestUser::withTrashed()->withoutTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
    }

    public function test_first_or_new()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $this->assertNull($result->id);

        $result = SoftDeletesTestUser::withTrashed()->firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $this->assertEquals(1, $result->id);
    }

    public function test_find_or_new()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::findOrNew(1);
        $this->assertNull($result->id);

        $result = SoftDeletesTestUser::withTrashed()->findOrNew(1);
        $this->assertEquals(1, $result->id);
    }

    public function test_first_or_create()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::withTrashed()->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
        $this->assertSame('taylorotwell@gmail.com', $result->email);
        $this->assertCount(1, SoftDeletesTestUser::all());

        $result = SoftDeletesTestUser::firstOrCreate(['email' => 'foo@bar.com']);
        $this->assertSame('foo@bar.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());
        $this->assertCount(3, SoftDeletesTestUser::withTrashed()->get());
    }

    public function test_create_or_first()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::withTrashed()->createOrFirst(['email' => 'taylorotwell@gmail.com']);
        $this->assertSame('taylorotwell@gmail.com', $result->email);
        $this->assertCount(1, SoftDeletesTestUser::all());

        $result = SoftDeletesTestUser::createOrFirst(['email' => 'foo@bar.com']);
        $this->assertSame('foo@bar.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());
        $this->assertCount(3, SoftDeletesTestUser::withTrashed()->get());
    }

    /**
     * @throws Exception
     */
    public function test_update_model_after_soft_deleting()
    {
        Carbon::setTestNow($now = Carbon::now());
        $this->createUsers();

        /** @var \Illuminate\Tests\Database\SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::find(2);
        $userModel->delete();
        $this->assertEquals($now->toDateTimeString(), $userModel->getOriginal('deleted_at'));
        $this->assertNull(SoftDeletesTestUser::find(2));
        $this->assertEquals($userModel, SoftDeletesTestUser::withTrashed()->find(2));
    }

    /**
     * @throws Exception
     */
    public function test_restore_after_soft_delete()
    {
        $this->createUsers();

        /** @var \Illuminate\Tests\Database\SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::find(2);
        $userModel->delete();
        $userModel->restore();

        $this->assertEquals($userModel->id, SoftDeletesTestUser::find(2)->id);
    }

    /**
     * @throws Exception
     */
    public function test_soft_delete_after_restoring()
    {
        $this->createUsers();

        /** @var \Illuminate\Tests\Database\SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::withTrashed()->find(1);
        $userModel->restore();
        $this->assertEquals($userModel->deleted_at, SoftDeletesTestUser::find(1)->deleted_at);
        $this->assertEquals($userModel->getOriginal('deleted_at'), SoftDeletesTestUser::find(1)->deleted_at);
        $userModel->delete();
        $this->assertNull(SoftDeletesTestUser::find(1));
        $this->assertEquals($userModel->deleted_at, SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
        $this->assertEquals($userModel->getOriginal('deleted_at'), SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
    }

    public function test_modifying_before_soft_deleting_and_restoring()
    {
        $this->createUsers();

        /** @var \Illuminate\Tests\Database\SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::find(2);
        $userModel->email = 'foo@bar.com';
        $userModel->delete();
        $userModel->restore();

        $this->assertEquals($userModel->id, SoftDeletesTestUser::find(2)->id);
        $this->assertSame('foo@bar.com', SoftDeletesTestUser::find(2)->email);
    }

    public function test_update_or_create()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::updateOrCreate(['email' => 'foo@bar.com'], ['email' => 'bar@baz.com']);
        $this->assertSame('bar@baz.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());

        $result = SoftDeletesTestUser::withTrashed()->updateOrCreate(['email' => 'taylorotwell@gmail.com'], ['email' => 'foo@bar.com']);
        $this->assertSame('foo@bar.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());
        $this->assertCount(3, SoftDeletesTestUser::withTrashed()->get());
    }

    public function test_has_one_relationship_can_be_soft_deleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $abigail->address()->create(['address' => 'Laravel avenue 43']);

        // delete on builder
        $abigail->address()->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
        $this->assertSame('Laravel avenue 43', $abigail->address()->withTrashed()->first()->address);

        // restore
        $abigail->address()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertSame('Laravel avenue 43', $abigail->address->address);

        // delete on model
        $abigail->address->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
        $this->assertSame('Laravel avenue 43', $abigail->address()->withTrashed()->first()->address);

        // force delete
        $abigail->address()->withTrashed()->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
    }

    public function test_belongs_to_relationship_can_be_soft_deleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $group = SoftDeletesTestGroup::create(['name' => 'admin']);
        $abigail->group()->associate($group);
        $abigail->save();

        // delete on builder
        $abigail->group()->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group);
        $this->assertSame('admin', $abigail->group()->withTrashed()->first()->name);

        // restore
        $abigail->group()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertSame('admin', $abigail->group->name);

        // delete on model
        $abigail->group->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group);
        $this->assertSame('admin', $abigail->group()->withTrashed()->first()->name);

        // force delete
        $abigail->group()->withTrashed()->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group()->withTrashed()->first());
    }

    public function test_has_many_relationship_can_be_soft_deleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $abigail->posts()->create(['title' => 'First Title']);
        $abigail->posts()->create(['title' => 'Second Title']);

        // delete on builder
        $abigail->posts()->where('title', 'Second Title')->delete();

        $abigail = $abigail->fresh();

        $this->assertCount(1, $abigail->posts);
        $this->assertSame('First Title', $abigail->posts->first()->title);
        $this->assertCount(2, $abigail->posts()->withTrashed()->get());

        // restore
        $abigail->posts()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertCount(2, $abigail->posts);

        // force delete
        $abigail->posts()->where('title', 'Second Title')->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertCount(1, $abigail->posts);
        $this->assertCount(1, $abigail->posts()->withTrashed()->get());
    }

    //    public function test_relation_to_sql_applies_soft_delete()
    //    {
    //        $this->createUsers();
    //
    //        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
    //
    //        $this->assertSame(
    //            'select * from "posts" where "posts"."user_id" = ? and "posts"."user_id" is not null and "posts"."deleted_at" is null',
    //            $abigail->posts()->toSql()
    //        );
    //    }

    public function test_relation_exists_and_doesnt_exist_honors_soft_delete()
    {
        $this->createUsers();
        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

        // 'exists' should return true before soft delete
        $abigail->posts()->create(['title' => 'First Title']);
        $this->assertTrue($abigail->posts()->exists());
        $this->assertFalse($abigail->posts()->doesntExist());

        // 'exists' should return false after soft delete
        $abigail->posts()->first()->delete();
        $this->assertFalse($abigail->posts()->exists());
        $this->assertTrue($abigail->posts()->doesntExist());

        // 'exists' should return true after restore
        $abigail->posts()->withTrashed()->restore();
        $this->assertTrue($abigail->posts()->exists());
        $this->assertFalse($abigail->posts()->doesntExist());

        // 'exists' should return false after a force delete
        $abigail->posts()->first()->forceDelete();
        $this->assertFalse($abigail->posts()->exists());
        $this->assertTrue($abigail->posts()->doesntExist());
    }

    public function test_relation_count_honors_soft_delete()
    {
        $this->createUsers();
        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

        // check count before soft delete
        $abigail->posts()->create(['title' => 'First Title']);
        $abigail->posts()->create(['title' => 'Second Title']);
        $this->assertEquals(2, $abigail->posts()->count());

        // check count after soft delete
        $abigail->posts()->where('title', 'Second Title')->delete();
        $this->assertEquals(1, $abigail->posts()->count());

        // check count after restore
        $abigail->posts()->withTrashed()->restore();
        $this->assertEquals(2, $abigail->posts()->count());

        // check count after a force delete
        $abigail->posts()->where('title', 'Second Title')->forceDelete();
        $this->assertEquals(1, $abigail->posts()->count());
    }

    public function test_relation_aggregates_honors_soft_delete()
    {
        $this->createUsers();
        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();

        // check aggregates before soft delete
        $abigail->posts()->create(['title' => 'First Title', 'priority' => 2]);
        $abigail->posts()->create(['title' => 'Second Title', 'priority' => 4]);
        $abigail->posts()->create(['title' => 'Third Title', 'priority' => 6]);
        $this->assertEquals(2, $abigail->posts()->min('priority'));
        $this->assertEquals(6, $abigail->posts()->max('priority'));
        $this->assertEquals(12, $abigail->posts()->sum('priority'));
        $this->assertEquals(4, $abigail->posts()->avg('priority'));

        // check aggregates after soft delete
        $abigail->posts()->where('title', 'First Title')->delete();
        $this->assertEquals(4, $abigail->posts()->min('priority'));
        $this->assertEquals(6, $abigail->posts()->max('priority'));
        $this->assertEquals(10, $abigail->posts()->sum('priority'));
        $this->assertEquals(5, $abigail->posts()->avg('priority'));

        // check aggregates after restore
        $abigail->posts()->withTrashed()->restore();
        $this->assertEquals(2, $abigail->posts()->min('priority'));
        $this->assertEquals(6, $abigail->posts()->max('priority'));
        $this->assertEquals(12, $abigail->posts()->sum('priority'));
        $this->assertEquals(4, $abigail->posts()->avg('priority'));

        // check aggregates after a force delete
        $abigail->posts()->where('title', 'Third Title')->forceDelete();
        $this->assertEquals(2, $abigail->posts()->min('priority'));
        $this->assertEquals(4, $abigail->posts()->max('priority'));
        $this->assertEquals(6, $abigail->posts()->sum('priority'));
        $this->assertEquals(3, $abigail->posts()->avg('priority'));
    }

    //    public function test_soft_delete_is_applied_to_new_query()
    //    {
    //        $query = (new SoftDeletesTestUser)->newQuery();
    //        $this->assertSame('select * from "users" where "users"."deleted_at" is null', $query->toSql());
    //    }

    public function test_second_level_relationship_can_be_soft_deleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->comments()->create(['body' => 'Comment Body']);

        $abigail->posts()->first()->comments()->delete();

        $abigail = $abigail->fresh();

        $this->assertCount(0, $abigail->posts()->first()->comments);
        $this->assertCount(1, $abigail->posts()->first()->comments()->withTrashed()->get());
    }

    public function test_where_has_with_deleted_relationship()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);

        $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->has('posts')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->has('posts')->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orHas('posts')->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
            $query->where('title', 'First Title');
        })->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
            $query->where('title', 'Another Title');
        })->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orWhereHas('posts', function ($query) {
            $query->where('title', 'First Title');
        })->get();
        $this->assertCount(1, $users);

        // With Post Deleted...

        $post->delete();
        $users = SoftDeletesTestUser::has('posts')->get();
        $this->assertCount(0, $users);
    }

    public function test_where_has_with_nested_deleted_relationship_and_only_trashed_condition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->delete();

        $users = SoftDeletesTestUser::has('posts')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
            $q->onlyTrashed();
        })->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
            $q->withTrashed();
        })->get();
        $this->assertCount(1, $users);
    }

    public function test_where_has_with_nested_deleted_relationship()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $comment = $post->comments()->create(['body' => 'Comment Body']);
        $comment->delete();

        $users = SoftDeletesTestUser::has('posts.comments')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
        $this->assertCount(1, $users);
    }

    public function test_where_doesnt_have_with_nested_deleted_relationship()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
        $this->assertCount(1, $users);
    }

    public function test_where_has_with_nested_deleted_relationship_and_with_trashed_condition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUserWithTrashedPosts::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->delete();

        $users = SoftDeletesTestUserWithTrashedPosts::has('posts')->get();
        $this->assertCount(1, $users);
    }

    public function test_with_count_with_nested_deleted_relationship_and_only_trashed_condition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->delete();
        $abigail->posts()->create(['title' => 'Second Title']);
        $abigail->posts()->create(['title' => 'Third Title']);

        $user = SoftDeletesTestUser::withCount('posts')->orderBy('posts_count', 'desc')->first();
        $this->assertEquals(2, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->onlyTrashed();
        }])->orderBy('posts_count', 'desc')->first();
        $this->assertEquals(1, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->withTrashed();
        }])->orderBy('posts_count', 'desc')->first();
        $this->assertEquals(3, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->withTrashed()->where('title', 'First Title');
        }])->orderBy('posts_count', 'desc')->first();
        $this->assertEquals(1, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->where('title', 'First Title');
        }])->orderBy('posts_count', 'desc')->first();
        $this->assertEquals(0, $user->posts_count);
    }

    public function test_or_where_with_soft_delete_constraint()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->orWhere('email', 'abigailotwell@gmail.com');
        $this->assertEquals(['abigailotwell@gmail.com'], $users->pluck('email')->all());
    }

    public function test_morph_to_with_trashed()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $abigail->delete();

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->withoutGlobalScope(SoftDeletingScope::class);
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->withTrashed();
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $comment = TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
            $q->withTrashed();
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);
    }

    public function test_morph_to_with_bad_method_call()
    {
        $this->expectException(BadMethodCallException::class);

        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);

        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
            $q->thisMethodDoesNotExist();
        }])->first();
    }

    public function test_morph_to_with_constraints()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->where('email', 'taylorotwell@gmail.com');
        }])->first();

        $this->assertNull($comment->owner);
    }

    public function test_morph_to_without_constraints()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $abigail->delete();
        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertNull($comment->owner);
    }

    public function test_morph_to_non_soft_deleting_model()
    {
        $taylor = TestUserWithoutSoftDelete::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post1 = $taylor->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => TestUserWithoutSoftDelete::class,
            'owner_id' => $taylor->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertEquals($taylor->email, $comment->owner->email);

        $taylor->delete();
        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertNull($comment->owner);
    }

    public function test_self_referencing_relationship_with_soft_deletes()
    {
        // https://github.com/laravel/framework/issues/42075
        [$taylor, $abigail] = $this->createUsers();

        $this->assertCount(1, $abigail->self_referencing);
        $this->assertTrue($abigail->self_referencing->first()->is($taylor));

        $this->assertCount(0, $taylor->self_referencing);
        $this->assertEquals(1, SoftDeletesTestUser::whereHas('self_referencing')->count());
    }

    /**
     * Helpers...
     *
     * @return \Illuminate\Tests\Database\SoftDeletesTestUser[]
     */
    protected function createUsers()
    {
        $taylor = SoftDeletesTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $abigail = SoftDeletesTestUser::create(['email' => 'abigailotwell@gmail.com']);

        $taylor->user_id = $abigail->id;
        $taylor->save();

        $taylor->delete();

        return [$taylor, $abigail];
    }

    /**
     * Get a database connection instance.
     *
     * @return Connection
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
class TestUserWithoutSoftDelete extends Eloquent
{
    protected $table = 'users';

    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestUser extends Eloquent
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    public function self_referencing()
    {
        return $this->hasMany(SoftDeletesTestUser::class, 'user_id')->onlyTrashed();
    }

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }

    public function address()
    {
        return $this->hasOne(SoftDeletesTestAddress::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(SoftDeletesTestGroup::class, 'group_id');
    }
}

class SoftDeletesTestUserWithTrashedPosts extends Eloquent
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id')->withTrashed();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestPost extends Eloquent
{
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];

    public function comments()
    {
        return $this->hasMany(SoftDeletesTestComment::class, 'post_id');
    }
}

/**
 * Eloquent Models...
 */
class TestCommentWithoutSoftDelete extends Eloquent
{
    protected $table = 'comments';

    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestComment extends Eloquent
{
    use SoftDeletes;

    protected $table = 'comments';

    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

class SoftDeletesTestCommentWithTrashed extends Eloquent
{
    use SoftDeletes;

    protected $table = 'comments';

    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestAddress extends Eloquent
{
    use SoftDeletes;

    protected $table = 'addresses';

    protected $guarded = [];
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestGroup extends Eloquent
{
    use SoftDeletes;

    protected $table = 'groups';

    protected $guarded = [];

    public function users()
    {
        $this->hasMany(SoftDeletesTestUser::class);
    }
}
