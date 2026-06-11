<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class GroupLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('group_limit_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('group_limit_children', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
        });

        DB::table('group_limit_users')->insert([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]);

        DB::table('group_limit_children')->insert([
            ['user_id' => 1, 'name' => 'Alice first'],
            ['user_id' => 1, 'name' => 'Alice second'],
            ['user_id' => 1, 'name' => 'Alice third'],
            ['user_id' => 2, 'name' => 'Bob first'],
            ['user_id' => 2, 'name' => 'Bob second'],
            ['user_id' => 2, 'name' => 'Bob third'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('group_limit_children');
        Schema::dropIfExists('group_limit_users');

        parent::tearDown();
    }

    #[Test]
    public function it_limits_results_per_group()
    {
        $results = DB::table('group_limit_children')
            ->select('id', 'user_id', 'name')
            ->orderByDesc('id')
            ->groupLimit(2, 'user_id')
            ->get();

        $this->assertSame(
            ['Alice third', 'Alice second'],
            $results->where('user_id', 1)->pluck('name')->all()
        );
        $this->assertSame(
            ['Bob third', 'Bob second'],
            $results->where('user_id', 2)->pluck('name')->all()
        );
    }

    #[Test]
    public function it_applies_an_offset_per_group()
    {
        $results = DB::table('group_limit_children')
            ->select('id', 'user_id', 'name')
            ->orderByDesc('id')
            ->offset(1)
            ->groupLimit(1, 'user_id')
            ->get();

        $this->assertSame(['Alice second'], $results->where('user_id', 1)->pluck('name')->all());
        $this->assertSame(['Bob second'], $results->where('user_id', 2)->pluck('name')->all());
    }

    #[Test]
    public function it_limits_results_per_group_without_an_explicit_order()
    {
        $results = DB::table('group_limit_children')
            ->groupLimit(1, 'user_id')
            ->get();

        $this->assertCount(1, $results->where('user_id', 1));
        $this->assertCount(1, $results->where('user_id', 2));
    }

    #[Test]
    public function it_limits_eager_loaded_has_many_relations_per_parent()
    {
        $users = GroupLimitUser::query()
            ->with([
                'children' => fn ($query) => $query->orderByDesc('id')->limit(2),
            ])
            ->orderBy('id')
            ->get();

        $this->assertSame(
            ['Alice third', 'Alice second'],
            $users[0]->children->pluck('name')->all()
        );
        $this->assertSame(
            ['Bob third', 'Bob second'],
            $users[1]->children->pluck('name')->all()
        );
    }
}

class GroupLimitUser extends Model
{
    public $timestamps = false;

    protected $table = 'group_limit_users';

    protected $guarded = [];

    public function children(): HasMany
    {
        return $this->hasMany(GroupLimitChild::class, 'user_id');
    }
}

class GroupLimitChild extends Model
{
    public $timestamps = false;

    protected $table = 'group_limit_children';

    protected $guarded = [];
}
