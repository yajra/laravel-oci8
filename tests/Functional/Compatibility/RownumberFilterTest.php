<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;

class RownumberFilterTest extends TestCase
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
            /** @var User $user */
            User::query()->create([
                'name' => 'Record-'.$i,
                'email' => 'Email-'.$i.'@example.com',
            ]);
        });
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('users')) {
            Schema::drop('users');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_removes_rownumber_in_pagination()
    {
        $expected = User::query()->limit(2)->orderBy('id')->get()->toArray();
        $this->assertArrayNotHasKey('rn', $expected[0]);
        $this->assertArrayNotHasKey('rn', $expected[1]);
    }
}
