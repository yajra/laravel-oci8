<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Oci8Connection;
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

    #[Test]
    public function it_can_resolve_oracle_eloquent_sequence_names()
    {
        $model = new MultiBlob;

        $this->assertSame('multi_blobs_id_seq', $model->getSequenceName());
        $this->assertSame($model, $model->setSequenceName('custom_multi_blob_seq'));
        $this->assertSame('custom_multi_blob_seq', $model->getSequenceName());
    }

    #[Test]
    public function it_can_get_the_next_value_from_an_oracle_eloquent_sequence()
    {
        $connection = DB::connection();

        if (! $connection instanceof Oci8Connection) {
            $this->assertSame(0, MultiBlob::nextValue('oracle_eloquent_test_seq'));

            return;
        }

        $sequence = $connection->getSequence();
        $sequence->forceCreate('oracle_eloquent_test_seq');

        try {
            $this->assertSame(1, MultiBlob::nextValue('oracle_eloquent_test_seq'));
            $this->assertSame(2, MultiBlob::nextValue('oracle_eloquent_test_seq'));
        } finally {
            $sequence->drop('oracle_eloquent_test_seq');
        }
    }

    #[Test]
    public function it_can_qualify_the_key_name_for_database_link_tables()
    {
        $model = new class extends MultiBlob
        {
            protected $table = 'users@remote';
        };

        $this->assertSame('users.id@remote', $model->getQualifiedKeyName());
    }

    #[Test]
    public function it_returns_false_when_updating_a_model_that_does_not_exist()
    {
        $model = new MultiBlob;

        $this->assertFalse($model->update(['status' => 1]));
    }

    #[Test]
    public function it_can_update_binary_fields_using_the_public_update_method()
    {
        $multiBlob = MultiBlob::create(['status' => 1]);

        $result = $multiBlob->update([
            'blob_1' => 'updated',
            'blob_2' => 'updated2',
            'status' => 2,
        ]);

        $this->assertTrue($result);
        $this->assertDatabaseHas('multi_blobs', [
            'id' => $multiBlob->id,
            'status' => 2,
        ]);
    }
}
