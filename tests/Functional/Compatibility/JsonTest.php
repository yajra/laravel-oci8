<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class JsonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('json_test', function (Blueprint $table) {
            $table->id('id');
            $table->json('options');
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('json_test');

        parent::tearDown();
    }

    #[Test]
    public function it_finds_rows_with_where()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

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
    public function it_finds_rows_with_where_case_sensitive()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        DB::table('json_test')->insert([
            'options' => json_encode(['Language' => 'en']),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['Language' => 'de']),
        ]);

        $results = DB::table('json_test')
            ->where('options->Language', 'en')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_filter_json_boolean_values()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['active' => true])],
            ['options' => json_encode(['active' => false])],
            ['options' => json_encode(['active' => true])],
        ]);

        $trueIds = DB::table('json_test')
            ->where('options->active', true)
            ->get();

        $falseIds = DB::table('json_test')
            ->where('options->active', false)
            ->get();

        $this->assertCount(2, $trueIds);
        $this->assertCount(1, $falseIds);
    }

    #[Test]
    public function it_can_filter_json_boolean_values_case_sensitive()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['Active' => true])],
            ['options' => json_encode(['Active' => false])],
            ['options' => json_encode(['Active' => true])],
        ]);

        $trueIds = DB::table('json_test')
            ->where('options->Active', true)
            ->get();

        $falseIds = DB::table('json_test')
            ->where('options->Active', false)
            ->get();

        $this->assertCount(2, $trueIds);
        $this->assertCount(1, $falseIds);
    }

    #[Test]
    public function it_finds_rows_where_json_contains_root_value()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        DB::table('json_test')->insert([
            'options' => json_encode(['en', 'de']),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['fr', 'hu', 'en']),
        ]);

        $results = DB::table('json_test')
            ->whereJsonContains('options', ['hu', 'fr'])
            ->get();

        $this->assertCount(1, $results);

        $results = DB::table('json_test')
            ->whereJsonContains('options', 'en')
            ->get();

        $this->assertCount(2, $results);

        $results = DB::table('json_test')
            ->whereJsonContains('options', ['en'])
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_contains_nested_value()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

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
    public function it_finds_rows_where_json_contains_2_level_nested_value()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

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
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

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
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

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

    #[Test]
    public function it_works_with_multiple_json_contains()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped('This is only supported from 12c and onward!');
        }

        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['fr']]),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['en']]),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['languages' => ['hu']]),
        ]);

        $results = DB::table('json_test')
            ->whereJsonContains('options->languages', ['en'])
            ->orWhereJsonContains('options->languages', ['fr'])
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_finds_rows_with_json_length_equal_to_1()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            'options' => json_encode(['en']),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['de', 'hu']),
        ]);

        $results = DB::table('json_test')
            ->whereJsonLength('options', '=', 1)
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_finds_rows_with_json_length_greater_than_1()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            'options' => json_encode(['en']),
        ]);

        DB::table('json_test')->insert([
            'options' => json_encode(['de', 'hu']),
        ]);

        $results = DB::table('json_test')
            ->whereJsonLength('options', '>', 1)
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_returns_zero_results_for_empty_json_array()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            'options' => json_encode([]),
        ]);

        $results = DB::table('json_test')
            ->whereJsonLength('options', '>', 0)
            ->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_doesnt_contain_value()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['en', 'de'])],
            ['options' => json_encode(['fr'])],
        ]);

        $results = DB::table('json_test')
            ->whereJsonDoesntContain('options', 'en')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_works_with_or_where_json_doesnt_contain()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['en'])],
            ['options' => json_encode(['fr'])],
        ]);

        $results = DB::table('json_test')
            ->where('id', 9999)
            ->orWhereJsonDoesntContain('options', 'en')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_contains_key()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['language' => 'en'])],
            ['options' => json_encode(['active' => true])],
        ]);

        $results = DB::table('json_test')
            ->whereJsonContainsKey('options->language')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_works_with_or_where_json_contains_key()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['language' => 'en'])],
            ['options' => json_encode(['active' => true])],
        ]);

        $results = DB::table('json_test')
            ->where('id', 9999)
            ->orWhereJsonContainsKey('options->active')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_finds_rows_where_json_doesnt_contain_key()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['language' => 'en'])],
            ['options' => json_encode(['active' => true])],
        ]);

        $results = DB::table('json_test')
            ->whereJsonDoesntContainKey('options->language')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_works_with_or_where_json_doesnt_contain_key()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['language' => 'en'])],
            ['options' => json_encode(['active' => true])],
        ]);

        $results = DB::table('json_test')
            ->where('id', 9999)
            ->orWhereJsonDoesntContainKey('options->language')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_works_with_or_where_json_length()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('12c')) {
            $this->markTestSkipped();
        }

        DB::table('json_test')->insert([
            ['options' => json_encode(['en'])],
            ['options' => json_encode(['en', 'de'])],
        ]);

        $results = DB::table('json_test')
            ->where('id', 9999)
            ->orWhereJsonLength('options', '>', 1)
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_update_json_paths()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('19c')) {
            $this->markTestSkipped('JSON path updates require Oracle 19c or newer.');
        }

        DB::table('json_test')->insert([
            'options' => json_encode([
                'language' => 'en',
                'settings' => ['theme' => 'light'],
                'active' => false,
                'tags' => ['old'],
                'nullable' => 'value',
                'score' => 1,
                'ratio' => 0.5,
            ]),
        ]);

        $id = DB::table('json_test')->value('id');

        foreach ([
            'options->language' => 'hu',
            'options->settings->theme' => 'dark',
            'options->active' => true,
            'options->nullable' => null,
            'options->score' => 42,
            'options->ratio' => 4.5,
        ] as $path => $value) {
            DB::table('json_test')->where('id', $id)->update([$path => $value]);
        }

        $options = json_decode((string) DB::table('json_test')->where('id', $id)->value('options'), true);

        $this->assertSame('hu', $options['language']);
        $this->assertSame('dark', $options['settings']['theme']);
        $this->assertTrue($options['active']);
        $this->assertNull($options['nullable']);
        $this->assertSame(42, $options['score']);

        if (DB::connection()->getDriverName() === 'mariadb') {
            $this->assertEquals(4.5, $options['ratio']);
        } else {
            $this->assertSame(4.5, $options['ratio']);
        }
    }

    #[Test]
    public function it_can_update_json_paths_with_array_values()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('19c')) {
            $this->markTestSkipped('JSON path updates require Oracle 19c or newer.');
        }

        if (DB::connection()->getDriverName() === 'mariadb') {
            $this->markTestSkipped('MariaDB stores Collection JSON path update values as strings through Laravel JSON_SET.');
        }

        DB::table('json_test')->insert([
            'options' => json_encode([
                'settings' => [
                    'theme' => 'light',
                    'notifications' => [
                        'email' => false,
                    ],
                ],
            ]),
        ]);

        $id = DB::table('json_test')->value('id');

        $settings = collect([
            'theme' => 'dark',
            'notifications' => [
                'email' => true,
            ],
        ]);

        DB::table('json_test')->where('id', $id)->update(['options->settings' => $settings]);

        $options = json_decode((string) DB::table('json_test')->where('id', $id)->value('options'), true);

        $this->assertSame('dark', $options['settings']['theme']);
        $this->assertTrue($options['settings']['notifications']['email']);
    }

    #[Test]
    public function it_can_update_multilevel_json_paths()
    {
        if (DB::connection()->getDriverName() === 'oracle' && DB::connection()->isVersionBelow('19c')) {
            $this->markTestSkipped('JSON path updates require Oracle 19c or newer.');
        }

        DB::table('json_test')->insert([
            'options' => json_encode([
                'profile' => [
                    'settings' => [
                        'theme' => 'light',
                        'notifications' => [
                            'email' => false,
                            'sms' => true,
                        ],
                        'attempts' => 1,
                    ],
                    'languages' => ['en'],
                ],
                'items' => [
                    ['name' => 'old', 'count' => 1],
                ],
            ]),
        ]);

        $id = DB::table('json_test')->value('id');

        foreach ([
            'options->profile->settings->theme' => 'dark',
            'options->profile->settings->notifications->email' => true,
            'options->profile->settings->notifications->sms' => null,
            'options->profile->settings->attempts' => 7,
            'options->items[0]->name' => 'new',
            'options->items[0]->count' => 12,
        ] as $path => $value) {
            DB::table('json_test')->where('id', $id)->update([$path => $value]);
        }

        $options = json_decode((string) DB::table('json_test')->where('id', $id)->value('options'), true);

        $this->assertSame('dark', $options['profile']['settings']['theme']);
        $this->assertTrue($options['profile']['settings']['notifications']['email']);
        $this->assertNull($options['profile']['settings']['notifications']['sms']);
        $this->assertSame(7, $options['profile']['settings']['attempts']);
        $this->assertSame('new', $options['items'][0]['name']);
        $this->assertSame(12, $options['items'][0]['count']);
    }
}
