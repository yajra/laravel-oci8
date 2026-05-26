<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class WhereIntegerInRawTest extends TestCase
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
    public function it_can_run_where_integer_in_raw_with_more_than_one_thousand_values()
    {
        $results = DB::table('users')
            ->whereIntegerInRaw('id', range(1, 5000))
            ->orderBy('id')
            ->get();

        $this->assertCount(20, $results);
    }

    #[Test]
    public function it_can_run_where_integer_not_in_raw_with_more_than_one_thousand_values()
    {
        $results = DB::table('users')
            ->whereIntegerNotInRaw('id', range(1, 5000))
            ->orderBy('id')
            ->get();

        $this->assertCount(0, $results);
    }
}
