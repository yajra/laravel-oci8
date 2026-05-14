<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class UnionLimitOffsetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        collect(range(1, 20))->each(function ($i) {
            DB::table('users')->insert([
                'name' => 'Record-'.$i,
                'email' => 'Email-'.$i.'@example.com',
            ]);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function it_can_perform_union_with_limit()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->limit(5)
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_can_perform_union_with_offset()
    {
        if ($this->isMariaDb()) {
            $this->markTestSkipped('MariaDB does not support OFFSET without LIMIT.');
        }

        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->skip(15)
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_can_perform_union_with_limit_and_offset()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->skip(5)->take(10)
            ->get();

        $this->assertCount(10, $results);
        $this->assertGreaterThanOrEqual(6, $results->first()->id);
    }

    #[Test]
    public function it_can_perform_union_with_order_by_and_limit()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->orderBy('id', 'asc')->take(3)
            ->get();

        $this->assertCount(3, $results);
        $this->assertEquals(1, $results->first()->id);
        $this->assertEquals(2, $results->get(1)->id);
        $this->assertEquals(3, $results->last()->id);
    }

    #[Test]
    public function it_can_perform_union_with_order_by_limit_and_offset()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->orderBy('id')->skip(5)->take(4)
            ->get();

        $this->assertCount(4, $results);
        $this->assertEquals([6, 7, 8, 9], $results->pluck('id')->all());
    }

    #[Test]
    public function it_can_perform_union_with_order_by_inside_union_query()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', 1)
            ->union(
                DB::query()
                    ->select('id', 'name')->from('users')->where('id', '>', 10)
                    ->orderBy('id', 'desc')->limit(2)
            )
            ->orderBy('id')
            ->get();

        $this->assertEquals([1, 19, 20], $results->pluck('id')->all());
    }

    #[Test]
    public function it_can_perform_union_all_with_limit()
    {
        $results = DB::query()
            ->select('name')->from('users')->where('id', '<=', 5)
            ->unionAll(DB::query()->select('name')->from('users')->where('id', '<=', 5))
            ->take(7)
            ->get();

        $this->assertCount(7, $results);
    }

    #[Test]
    public function it_can_perform_multiple_unions_with_limit()
    {
        $results = DB::query()
            ->select('id')->from('users')->where('id', '<=', 5)
            ->union(DB::query()->select('id')->from('users')->where('id', '>', 5)->where('id', '<=', 10))
            ->union(DB::query()->select('id')->from('users')->where('id', '>', 10)->where('id', '<=', 15))
            ->limit(8)
            ->get();

        $this->assertCount(8, $results);
    }

    #[Test]
    public function it_can_perform_union_with_limit_one()
    {
        $results = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->limit(1)
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_paginate_union_results()
    {
        $page1 = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->skip(0)->take(5)
            ->get();
        $this->assertCount(5, $page1);

        $page2 = DB::query()
            ->select('id', 'name')->from('users')->where('id', '<=', 10)
            ->union(DB::query()->select('id', 'name')->from('users')->where('id', '>', 10))
            ->skip(5)->take(5)
            ->get();
        $this->assertCount(5, $page2);

        $this->assertNotEquals($page1->first()->id, $page2->first()->id);
    }
}
