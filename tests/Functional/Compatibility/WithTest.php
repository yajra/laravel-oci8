<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\Functional\Compatibility\Model\Child;
use Yajra\Oci8\Tests\Functional\Compatibility\Model\User;
use Yajra\Oci8\Tests\TestCase;

class WithTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_works_with_exists()
    {
        Schema::create('children', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertFalse(User::withExists('children')->find($user->id)->children_exists);

        Child::create([
            'name' => 'child',
            'user_id' => $user->id,
        ]);

        $this->assertTrue(User::withExists('children')->find($user->id)->children_exists);

    }
}
