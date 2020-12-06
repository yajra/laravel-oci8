<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;

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
