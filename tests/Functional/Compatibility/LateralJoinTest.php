<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Yajra\Oci8\Tests\TestCase;

class LateralJoinTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function it_supports_join_lateral_when_the_driver_does()
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'oracle' && $connection->isVersionBelow('12c')) {
            $this->markTestSkipped('Lateral joins are only supported by Oracle 12c and onward.');
        }

        $this->seedUsersAndPosts();

        $query = DB::table('users')
            ->select('users.name', 'latest_post.latest_title')
            ->joinLateral(
                DB::table('posts')
                    ->select('title as latest_title')
                    ->whereColumn('posts.user_id', 'users.id')
                    ->orderByDesc('posts.id')
                    ->limit(1),
                'latest_post'
            )
            ->orderBy('users.id');

        if ($this->isMariaDb()) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('This database engine does not support lateral joins.');
            $query->get();

            return;
        }

        $results = array_map(function ($row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);

            return [
                'name' => $row['name'],
                'latest_title' => $row['latest_title'] ?? null,
            ];
        }, $query->get()->all());

        $this->assertSame([
            ['name' => 'Alice', 'latest_title' => 'Alice second'],
            ['name' => 'Bob', 'latest_title' => 'Bob first'],
        ], $results);
    }

    #[Test]
    public function it_supports_left_join_lateral_when_the_driver_does()
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'oracle' && $connection->isVersionBelow('12c')) {
            $this->markTestSkipped('Lateral joins are only supported by Oracle 12c and onward.');
        }

        $this->seedUsersAndPosts();

        DB::table('users')->insert([
            'name' => 'Charlie',
        ]);

        $query = DB::table('users')
            ->select('users.name', 'latest_post.latest_title')
            ->leftJoinLateral(
                DB::table('posts')
                    ->select('title as latest_title')
                    ->whereColumn('posts.user_id', 'users.id')
                    ->orderByDesc('posts.id')
                    ->limit(1),
                'latest_post'
            )
            ->orderBy('users.id');

        if ($this->isMariaDb()) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('This database engine does not support lateral joins.');
            $query->get();

            return;
        }

        $results = array_map(function ($row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);

            return [
                'name' => $row['name'],
                'latest_title' => $row['latest_title'] ?? null,
            ];
        }, $query->get()->all());

        $this->assertSame([
            ['name' => 'Alice', 'latest_title' => 'Alice second'],
            ['name' => 'Bob', 'latest_title' => 'Bob first'],
            ['name' => 'Charlie', 'latest_title' => null],
        ], $results);
    }

    protected function seedUsersAndPosts(): void
    {
        DB::table('users')->insert([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]);

        DB::table('posts')->insert([
            ['user_id' => 1, 'title' => 'Alice first'],
            ['user_id' => 1, 'title' => 'Alice second'],
            ['user_id' => 2, 'title' => 'Bob first'],
        ]);
    }
}
