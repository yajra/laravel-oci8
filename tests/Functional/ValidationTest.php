<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;

class ValidationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_works_with_unique_case_insensitive_validation()
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'John Doe', 'email' => 'johndoe@example.com']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'John doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'john doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'test doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_works_with_exists_case_insensitive_validation()
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertTrue($validator->fails());

        User::create(['name' => 'John Doe', 'email' => 'johndoe@example.com']);
        User::create(['name' => 'Test Name', 'email' => 'testname@example.com']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => 'john Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => ['test name', 'john doe']],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());
    }
}
