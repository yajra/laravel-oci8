<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class QualifiedUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('qualified_update_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('options')->nullable();
        });

        DB::table('qualified_update_users')->insert([
            'name' => 'Alice',
            'options' => json_encode(['language' => 'en']),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('qualified_update_users');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_a_qualified_column(): void
    {
        $updated = DB::table('qualified_update_users')
            ->where('qualified_update_users.id', '=', 1)
            ->update(['qualified_update_users.name' => 'Updated']);

        $this->assertSame(1, $updated);
        $this->assertSame('Updated', DB::table('qualified_update_users')->value('name'));
    }

    #[Test]
    public function it_updates_a_qualified_json_selector(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'oracle' && $connection->isVersionBelow('19c')) {
            $this->markTestSkipped('Oracle JSON path updates require Oracle 19c or newer.');
        }

        $updated = DB::table('qualified_update_users')
            ->where('qualified_update_users.id', '=', 1)
            ->update(['qualified_update_users.options->language' => 'de']);

        $this->assertSame(1, $updated);
        $this->assertSame(
            'de',
            DB::table('qualified_update_users')
                ->where('id', '=', 1)
                ->value('options->language')
        );
    }
}
