<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\UserWithGuardedProperty;

class ModelTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_insert_by_setting_the_property()
    {
        $count = UserWithGuardedProperty::count();

        $user        = new UserWithGuardedProperty;
        $user->name  = 'Test';
        $user->email = 'test@example.com';
        $user->save();

        $this->assertDatabaseCount('users', $count + 1);
    }

    /** @test */
    public function it_can_insert_using_create_method()
    {
        $count = UserWithGuardedProperty::count();

        UserWithGuardedProperty::create([
            'name'  => 'Test',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseCount('users', $count + 1);
    }
}
