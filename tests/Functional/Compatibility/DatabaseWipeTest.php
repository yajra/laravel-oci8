<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DatabaseWipeTest extends TestCase
{
    protected function tearDown(): void
    {

        if (Schema::hasTable('wipe_posts')) {
            Schema::drop('wipe_posts');
        }
        if (Schema::hasTable('wipe_users')) {
            Schema::drop('wipe_users');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_wipe_identity_tables_without_dropping_system_generated_sequences(): void
    {
        Schema::create('wipe_users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });

        Schema::create('wipe_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue(Schema::hasTable('wipe_users'));
        $this->assertTrue(Schema::hasTable('wipe_posts'));

        $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('wipe_users'));
        $this->assertFalse(Schema::hasTable('wipe_posts'));
    }
}
