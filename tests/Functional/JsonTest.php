<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class JsonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_finds_rows_with_where()
    {
        DB::table('json_test')->insert([
            'options' => json_encode(['language' => 'en']),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['language' => 'de']),
        ]);

        $results = DB::table('json_test')
            ->where('options->language', 'en')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_contains_root_value()
    {
        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['en', 'de']]),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['fr', 'hu', 'en']]),
        ]);

        $results = DB::table('json_test')
            ->whereJsonContains('options->languages', ['hu', 'fr'])
            ->get();

        $this->assertCount(1, $results);

        $results = DB::table('json_test')
            ->whereJsonContains('options->languages', 'en')
            ->get();

        $this->assertCount(2, $results);

        $results = DB::table('json_test')
            ->whereJsonContains('options->languages', ['en'])
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_contains_nested_value()
    {
        DB::table('json_test')->insert([
            'options' => json_encode([
                'settings' => [
                    'languages' => ['en', 'es'],
                ],
            ]),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode([
                'settings' => [
                    'languages' => ['jp'],
                ],
            ]),
        ]);

        $results = DB::table('json_test')
            ->whereJsonContains('options->settings->languages', ['es'])
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_handles_multiple_levels_of_json_paths()
    {
        DB::table('json_test')->insert([
            'options' => json_encode([
                'profile' => [
                    'settings' => [
                        'languages' => ['en'],
                    ],
                ],
            ]),
        ]);

        $results = DB::table('json_test')
            ->whereJsonContains('options->profile->settings->languages', ['en'])
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_works_with_or_where_json_contains()
    {
        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['fr']]),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['en']]),
        ]);

        $results = DB::table('json_test')
            ->where('id', 99999)
            ->orWhereJsonContains('options->languages', ['en'])
            ->get();

        $this->assertCount(1, $results);
    }
}
