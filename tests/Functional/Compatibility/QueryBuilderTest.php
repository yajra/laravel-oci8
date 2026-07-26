<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('query_builder_posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->unique();
            $table->text('content');
            $table->timestamp('created_at');
        });

        DB::table('query_builder_posts')->insert([
            [
                'title' => 'Foo Post',
                'content' => 'Lorem Ipsum.',
                'created_at' => new Carbon('2017-11-12 13:14:15'),
            ],
            [
                'title' => 'Bar Post',
                'content' => 'Lorem Ipsum.',
                'created_at' => new Carbon('2018-01-02 03:04:05'),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropPostsAlias();
        Schema::dropIfExists('query_builder_accounting');
        Schema::dropIfExists('query_builder_archive');
        Schema::dropIfExists('query_builder_posts');

        parent::tearDown();
    }

    #[Test]
    public function it_can_increment_multiple_columns_and_update_other_attributes(): void
    {
        $this->createAccountingRows();

        $affected = DB::table('query_builder_accounting')
            ->where('user_id', 2)
            ->incrementEach([
                'wallet_1' => 10,
                'wallet_2' => -20,
            ], ['name' => 'Updated']);

        $this->assertSame(1, $affected);
        $this->assertEquals(
            ['wallet_1' => 25.0, 'wallet_2' => 280.0, 'name' => 'Updated'],
            (array) DB::table('query_builder_accounting')
                ->where('user_id', 2)
                ->first(['wallet_1', 'wallet_2', 'name'])
        );
        $this->assertEquals(
            ['wallet_1' => 100.0, 'wallet_2' => 200.0, 'name' => 'Taylor'],
            (array) DB::table('query_builder_accounting')
                ->where('user_id', 1)
                ->first(['wallet_1', 'wallet_2', 'name'])
        );
    }

    #[Test]
    public function it_can_decrement_multiple_columns_and_update_other_attributes(): void
    {
        $this->createAccountingRows();

        $affected = DB::table('query_builder_accounting')
            ->where('user_id', 2)
            ->decrementEach([
                'wallet_1' => 5,
                'wallet_2' => 20,
            ], ['name' => 'Updated']);

        $this->assertSame(1, $affected);
        $this->assertEquals(
            ['wallet_1' => 10.0, 'wallet_2' => 280.0, 'name' => 'Updated'],
            (array) DB::table('query_builder_accounting')
                ->where('user_id', 2)
                ->first(['wallet_1', 'wallet_2', 'name'])
        );
    }

    #[Test]
    public function it_can_return_the_only_matching_record(): void
    {
        $post = DB::table('query_builder_posts')
            ->where('title', 'Foo Post')
            ->sole(['id', 'title']);

        $this->assertEquals(['id' => 1, 'title' => 'Foo Post'], (array) $post);
    }

    #[Test]
    public function sole_throws_when_more_than_one_record_matches(): void
    {
        $this->expectExceptionObject(new MultipleRecordsFoundException(2));

        DB::table('query_builder_posts')->sole();
    }

    #[Test]
    public function sole_throws_when_no_record_matches(): void
    {
        $this->expectException(RecordsNotFoundException::class);

        DB::table('query_builder_posts')->where('title', 'Missing Post')->sole();
    }

    #[Test]
    public function it_can_select_and_add_scalar_subqueries(): void
    {
        $selected = DB::table('query_builder_posts')
            ->select('id')
            ->addSelect([
                'title',
                'copied_content' => DB::table('query_builder_posts as source')
                    ->select('source.content')
                    ->whereColumn('source.id', 'query_builder_posts.id'),
            ])
            ->orderBy('id')
            ->first();

        $this->assertEquals([
            'id' => 1,
            'title' => 'Foo Post',
            'copied_content' => 'Lorem Ipsum.',
        ], (array) $selected);
    }

    #[Test]
    public function it_can_query_an_aliased_subquery(): void
    {
        $posts = DB::query()
            ->fromSub(function ($query): void {
                $query->from('query_builder_posts')
                    ->select('id', 'title')
                    ->where('id', '>', 1);
            }, 'posts')
            ->where('posts.title', 'Bar Post')
            ->get();

        $this->assertCount(1, $posts);
        $this->assertSame('Bar Post', $posts->first()->title);
    }

    #[Test]
    public function it_can_compare_values_with_scalar_subqueries(): void
    {
        $subquery = DB::table('query_builder_posts')
            ->select('title')
            ->where('id', 1);

        $this->assertTrue(
            DB::table('query_builder_posts')
                ->where($subquery, 'Foo Post')
                ->exists()
        );
        $this->assertFalse(
            DB::table('query_builder_posts')
                ->where($subquery, 'Missing Post')
                ->exists()
        );
        $this->assertTrue(
            DB::table('query_builder_posts')
                ->where(DB::raw("'Foo Post'"), $subquery)
                ->exists()
        );
    }

    #[Test]
    public function it_can_negate_basic_and_nested_where_clauses(): void
    {
        $basic = DB::table('query_builder_posts')
            ->whereNot('title', 'Foo Post')
            ->pluck('title')
            ->all();

        $nested = DB::table('query_builder_posts')
            ->whereNot(function ($query): void {
                $query->where('title', 'Foo Post')
                    ->orWhere('created_at', '<', new Carbon('2017-01-01'));
            })
            ->pluck('title')
            ->all();

        $this->assertSame(['Bar Post'], $basic);
        $this->assertSame(['Bar Post'], $nested);
    }

    #[Test]
    public function it_can_filter_by_date(): void
    {
        $this->assertSame(
            1,
            DB::table('query_builder_posts')->whereDate('created_at', '2018-01-02')->count()
        );
        $this->assertSame(
            2,
            DB::table('query_builder_posts')
                ->where('id', 1)
                ->orWhereDate('created_at', new Carbon('2018-01-02'))
                ->count()
        );
    }

    #[Test]
    public function it_can_filter_by_day(): void
    {
        $this->assertSame(
            1,
            DB::table('query_builder_posts')->whereDay('created_at', 2)->count()
        );
        $this->assertSame(
            2,
            DB::table('query_builder_posts')
                ->where('id', 1)
                ->orWhereDay('created_at', new Carbon('2018-01-02'))
                ->count()
        );
    }

    #[Test]
    public function it_can_filter_by_month(): void
    {
        $this->assertSame(
            1,
            DB::table('query_builder_posts')->whereMonth('created_at', 1)->count()
        );
        $this->assertSame(
            2,
            DB::table('query_builder_posts')
                ->where('id', 1)
                ->orWhereMonth('created_at', new Carbon('2018-01-02'))
                ->count()
        );
    }

    #[Test]
    public function it_can_filter_by_year(): void
    {
        $this->assertSame(
            1,
            DB::table('query_builder_posts')->whereYear('created_at', 2018)->count()
        );
        $this->assertSame(
            2,
            DB::table('query_builder_posts')
                ->where('id', 1)
                ->orWhereYear('created_at', new Carbon('2018-01-02'))
                ->count()
        );
    }

    #[Test]
    public function it_can_filter_by_time(): void
    {
        $this->assertSame(
            1,
            DB::table('query_builder_posts')->whereTime('created_at', '03:04:05')->count()
        );
        $this->assertSame(
            2,
            DB::table('query_builder_posts')
                ->where('id', 1)
                ->orWhereTime('created_at', new Carbon('2018-01-02 03:04:05'))
                ->count()
        );
    }

    #[Test]
    public function it_can_paginate_a_specific_projection(): void
    {
        $result = DB::table('query_builder_posts')
            ->orderBy('id')
            ->paginate(1, ['title', 'content']);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(2, $result->total());
        $this->assertEquals(
            [(object) ['title' => 'Foo Post', 'content' => 'Lorem Ipsum.']],
            $result->items()
        );
    }

    #[Test]
    public function it_can_map_results_in_chunks(): void
    {
        $titles = DB::table('query_builder_posts')
            ->orderBy('id')
            ->chunkMap(fn ($post) => $post->title, 1);

        $this->assertSame(['Foo Post', 'Bar Post'], $titles->all());
    }

    #[Test]
    public function it_can_pluck_values_keys_and_scalar_subqueries(): void
    {
        $this->assertSame(
            ['Foo Post', 'Bar Post'],
            DB::table('query_builder_posts')->orderBy('id')->pluck('title')->all()
        );
        $this->assertSame(
            [1 => 'Foo Post', 2 => 'Bar Post'],
            DB::table('query_builder_posts')->orderBy('id')->pluck('title', 'id')->all()
        );

        $counts = DB::table('query_builder_posts')
            ->selectSub(
                DB::table('query_builder_posts')->selectRaw('count(*)'),
                'total_posts_count'
            )
            ->pluck('total_posts_count')
            ->map(fn ($count) => (int) $count)
            ->all();

        $this->assertSame([2, 2], $counts);
    }

    #[Test]
    public function it_can_fetch_a_column_using_a_pdo_fetch_mode(): void
    {
        $this->assertSame(
            ['Foo Post', 'Bar Post'],
            DB::table('query_builder_posts')
                ->orderBy('id')
                ->select('title')
                ->fetchUsing(PDO::FETCH_COLUMN)
                ->get()
                ->all()
        );
    }

    #[Test]
    public function it_can_fetch_key_value_pairs_using_a_pdo_fetch_mode(): void
    {
        $this->assertSame(
            [1 => 'Foo Post', 2 => 'Bar Post'],
            DB::table('query_builder_posts')
                ->orderBy('id')
                ->select('id', 'title')
                ->fetchUsing(PDO::FETCH_KEY_PAIR)
                ->get()
                ->all()
        );
    }

    #[Test]
    public function it_can_fetch_rows_keyed_by_the_first_column(): void
    {
        $posts = DB::table('query_builder_posts')
            ->orderBy('id')
            ->select('id', 'title')
            ->fetchUsing(PDO::FETCH_UNIQUE)
            ->get();

        $this->assertSame('Foo Post', $posts[1]->title);
        $this->assertSame('Bar Post', $posts[2]->title);
    }

    #[Test]
    public function it_can_insert_rows_from_a_subquery(): void
    {
        Schema::create('query_builder_archive', function (Blueprint $table): void {
            $table->integer('post_id');
            $table->string('title');
        });

        $inserted = DB::table('query_builder_archive')->insertUsing(
            ['post_id', 'title'],
            DB::table('query_builder_posts')
                ->select('id', 'title')
                ->where('id', 1)
        );

        $this->assertSame(1, $inserted);
        $this->assertDatabaseHas('query_builder_archive', [
            'post_id' => 1,
            'title' => 'Foo Post',
        ]);
    }

    #[Test]
    public function it_can_update_or_insert_rows(): void
    {
        $this->assertTrue(
            DB::table('query_builder_posts')->updateOrInsert(
                ['title' => 'Foo Post'],
                ['content' => 'Updated content.']
            )
        );
        $this->assertTrue(
            DB::table('query_builder_posts')->updateOrInsert(
                ['title' => 'New Post'],
                [
                    'content' => 'New content.',
                    'created_at' => new Carbon('2019-02-03 04:05:06'),
                ]
            )
        );

        $this->assertDatabaseHas('query_builder_posts', [
            'title' => 'Foo Post',
            'content' => 'Updated content.',
        ]);
        $this->assertDatabaseHas('query_builder_posts', [
            'title' => 'New Post',
            'content' => 'New content.',
        ]);
    }

    #[Test]
    public function it_can_upsert_multiple_rows(): void
    {
        DB::table('query_builder_posts')->upsert([
            [
                'title' => 'Foo Post',
                'content' => 'Upserted content.',
                'created_at' => new Carbon('2020-01-01 00:00:00'),
            ],
            [
                'title' => 'New Post',
                'content' => 'Inserted content.',
                'created_at' => new Carbon('2020-01-02 00:00:00'),
            ],
        ], ['title'], ['content']);

        $this->assertDatabaseHas('query_builder_posts', [
            'title' => 'Foo Post',
            'content' => 'Upserted content.',
        ]);
        $this->assertDatabaseHas('query_builder_posts', [
            'title' => 'New Post',
            'content' => 'Inserted content.',
        ]);
    }

    #[Test]
    public function it_can_query_and_update_through_a_database_indirection(): void
    {
        $this->createPostsAlias();

        $post = DB::table('query_builder_posts_alias as posts')
            ->select('posts.id', 'posts.title')
            ->where('posts.id', 2)
            ->first();

        $updated = DB::table('query_builder_posts_alias')
            ->where('id', 2)
            ->update(['content' => 'Updated through alias.']);

        $this->assertSame('Bar Post', $post->title);
        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('query_builder_posts', [
            'id' => 2,
            'content' => 'Updated through alias.',
        ]);
    }

    private function createAccountingRows(): void
    {
        Schema::create('query_builder_accounting', function (Blueprint $table): void {
            $table->increments('id');
            $table->float('wallet_1');
            $table->float('wallet_2');
            $table->integer('user_id');
            $table->string('name', 20);
        });

        DB::table('query_builder_accounting')->insert([
            ['wallet_1' => 100, 'wallet_2' => 200, 'user_id' => 1, 'name' => 'Taylor'],
            ['wallet_1' => 15, 'wallet_2' => 300, 'user_id' => 2, 'name' => 'Otwell'],
        ]);
    }

    private function createPostsAlias(): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement(
                'create synonym "QUERY_BUILDER_POSTS_ALIAS" for "QUERY_BUILDER_POSTS"'
            );

            return;
        }

        if ($this->isPgsql()) {
            DB::statement(
                'create view "query_builder_posts_alias" as select * from "query_builder_posts"'
            );

            return;
        }

        DB::statement(
            'create view query_builder_posts_alias as select * from query_builder_posts'
        );
    }

    private function dropPostsAlias(): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement(
                "begin execute immediate 'drop synonym \"QUERY_BUILDER_POSTS_ALIAS\"'; exception when others then null; end;"
            );

            return;
        }

        DB::statement('drop view if exists query_builder_posts_alias');
    }
}
