<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class DatabaseCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('cache')) {
            Schema::drop('cache');
        }

        if (Schema::hasTable('cache_locks')) {
            Schema::drop('cache_locks');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_put_and_get_a_value_in_database_cache(): void
    {
        Cache::put('test_key', 'test_value', 60);

        $this->assertSame(
            'test_value',
            Cache::get('test_key')
        );
    }

    #[Test]
    public function it_returns_null_for_missing_cache_value(): void
    {
        $this->assertNull(Cache::get('non_existent_key'));
    }

    #[Test]
    public function it_can_forget_a_cache_value(): void
    {
        Cache::put('forget_key', 'value', 60);

        $this->assertSame('value', Cache::get('forget_key'));

        Cache::forget('forget_key');

        $this->assertNull(Cache::get('forget_key'));
    }

    #[Test]
    public function it_respects_cache_expiration(): void
    {
        Cache::put('expiring_key', 'value', now()->addSecond());

        $this->assertSame('value', Cache::get('expiring_key'));

        sleep(2);

        $this->assertNull(Cache::get('expiring_key'));
    }

    #[Test]
    public function it_can_store_and_retrieve_large_values_over_4000_characters(): void
    {
        // Oracle CLOB boundary test (> 4000 chars)
        $longValue = str_repeat('A', 25000);

        Cache::put('large_value_key', $longValue, 60);

        $retrieved = Cache::get('large_value_key');

        $this->assertIsString($retrieved);
        $this->assertSame(25000, strlen($retrieved));
        $this->assertSame($longValue, $retrieved);
    }
}
