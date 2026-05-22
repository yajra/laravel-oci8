<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Auth\OracleUserProvider;
use Yajra\Oci8\Tests\TestCase;

class OracleUserProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });

        OracleUserProviderUser::query()->create([
            'name' => 'Jane Doe',
            'email' => 'Jane@example.com',
            'password' => 'secret',
        ]);

        OracleUserProviderUser::query()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'another-secret',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::drop('users');

        parent::tearDown();
    }

    #[Test]
    public function it_returns_null_when_credentials_are_empty(): void
    {
        $provider = new OracleUserProvider($this->app['hash'], OracleUserProviderUser::class);

        $this->assertNull($provider->retrieveByCredentials([]));
    }

    #[Test]
    public function it_retrieves_a_user_with_case_insensitive_credentials_and_ignores_password_fields(): void
    {
        $provider = new OracleUserProvider($this->app['hash'], OracleUserProviderUser::class);

        $user = $provider->retrieveByCredentials([
            'email' => 'jane@EXAMPLE.COM',
            'password' => 'wrong-password',
        ]);

        $this->assertInstanceOf(OracleUserProviderUser::class, $user);
        $this->assertSame('Jane Doe', $user->name);
    }
}

class OracleUserProviderUser extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'users';

    protected $guarded = [];
}
