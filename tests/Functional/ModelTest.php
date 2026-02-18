<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\MultiBlob;
use Yajra\Oci8\Tests\TestCase;
use Yajra\Oci8\Tests\User;
use Yajra\Oci8\Tests\UserWithGuardedProperty;

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('multi_blobs', function (Blueprint $table) {
            $table->increments('id');
            $table->binary('blob_1')->nullable();
            $table->binary('blob_2')->nullable();
            $table->integer('status')->nullable();
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
        Schema::drop('users');
        Schema::drop('multi_blobs');

        parent::tearDown();
    }

    #[Test]
    public function it_can_fill_a_model_to_create_a_record()
    {
        $attributes = [
            'name' => 'John',
            'email' => 'john@example.com',
        ];

        $model = new User;
        $model->fill($attributes);
        $model->save();

        $this->assertDatabaseHas('users', $attributes);
    }

    #[Test]
    public function it_can_insert_record_using_a_model()
    {
        User::query()->insert($attributes = [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('users', $attributes);
    }

    #[Test]
    public function it_can_create_guarded_model_by_setting_the_property()
    {
        $count = UserWithGuardedProperty::count();

        $user = new UserWithGuardedProperty;
        $user->name = 'Test';
        $user->email = 'test@example.com';
        $user->save();

        $this->assertDatabaseCount('users', $count + 1);
    }

    #[Test]
    public function it_can_create_guarded_model_using_create_method()
    {
        $count = UserWithGuardedProperty::count();

        UserWithGuardedProperty::create([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseCount('users', $count + 1);
    }

    #[Test]
    public function it_can_update_model_with_multiple_blob_columns()
    {
        $multiBlob = MultiBlob::create();
        $multiBlob->blob_1 = ['test'];
        $multiBlob->blob_2 = ['test2'];
        $multiBlob->status = 1;
        $multiBlob->save();

        $this->assertDatabaseHas('multi_blobs', ['status' => 1]);
    }
}
