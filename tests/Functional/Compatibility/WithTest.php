<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class WithTest extends TestCase
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

        Schema::create('children', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->integer('score')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');

        Schema::dropIfExists('children');

        parent::tearDown();
    }

    #[Test]
    public function it_works_with_exists()
    {
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

    #[Test]
    public function it_works_with_count()
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertEquals(0, User::withCount('children')->find($user->id)->children_count);

        for ($i = 1; $i <= 10; $i++) {
            Child::create([
                'name' => 'child'.$i,
                'user_id' => $user->id,
            ]);
        }

        $this->assertEquals(10, User::withCount('children')->find($user->id)->children_count);

    }

    #[Test]
    public function it_works_with_min()
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertNull(
            User::withMin('children', 'score')->find($user->id)->children_min_score
        );

        Child::create(['name' => 'c1', 'user_id' => $user->id, 'score' => 10]);
        Child::create(['name' => 'c2', 'user_id' => $user->id, 'score' => 5]);
        Child::create(['name' => 'c3', 'user_id' => $user->id, 'score' => 20]);

        $this->assertEquals(
            5,
            User::withMin('children', 'score')->find($user->id)->children_min_score
        );
    }

    #[Test]
    public function it_works_with_max()
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertNull(
            User::withMax('children', 'score')->find($user->id)->children_max_score
        );

        Child::create(['name' => 'c1', 'user_id' => $user->id, 'score' => 10]);
        Child::create(['name' => 'c2', 'user_id' => $user->id, 'score' => 30]);
        Child::create(['name' => 'c3', 'user_id' => $user->id, 'score' => 20]);

        $this->assertEquals(
            30,
            User::withMax('children', 'score')->find($user->id)->children_max_score
        );
    }

    #[Test]
    public function it_works_with_avg()
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertNull(
            User::withAvg('children', 'score')->find($user->id)->children_avg_score
        );

        Child::create(['name' => 'c1', 'user_id' => $user->id, 'score' => 10]);
        Child::create(['name' => 'c2', 'user_id' => $user->id, 'score' => 20]);
        Child::create(['name' => 'c3', 'user_id' => $user->id, 'score' => 30]);

        $this->assertEquals(
            20,
            User::withAvg('children', 'score')->find($user->id)->children_avg_score
        );
    }

    #[Test]
    public function it_works_with_sum()
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'test@test.hu',
        ]);

        $this->assertEquals(
            0,
            User::withSum('children', 'score')->find($user->id)->children_sum_score
        );

        Child::create(['name' => 'c1', 'user_id' => $user->id, 'score' => 10]);
        Child::create(['name' => 'c2', 'user_id' => $user->id, 'score' => 15]);
        Child::create(['name' => 'c3', 'user_id' => $user->id, 'score' => 25]);

        $this->assertEquals(
            50,
            User::withSum('children', 'score')->find($user->id)->children_sum_score
        );
    }
}

/**
 * @property int id
 * @property string name
 * @property int user_id
 */
class Child extends Model
{
    protected $guarded = [];
}

/**
 * @property int id
 * @property string name
 * @property string email
 */
class User extends Model
{
    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(Child::class);
    }
}
