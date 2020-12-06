<?php

namespace Yajra\Oci8\Tests\Functional;

use Yajra\Oci8\Tests\User;
use Yajra\Oci8\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DatabasePresenceVerifierTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_works()
    {
        $users = User::all();

        $this->assertEquals($users->count(), 20);
    }
}
