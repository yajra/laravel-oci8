<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use PHPStan\Analyser\Analyser;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// Larastan boots Laravel and registers global handlers and Artisan callbacks.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PHPStanBuilderExtensionTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/phpstan-hints.neon'];
    }

    protected function tearDown(): void
    {
        HandleExceptions::flushState($this);

        parent::tearDown();
    }

    public function test_hint_preserves_builder_and_model_types(): void
    {
        // Data providers run in the parent process, so analysis must happen inside the test.
        foreach (self::gatherAssertTypes(__DIR__.'/Fixtures/optimizer-hints.php') as $assert) {
            $this->assertFileAsserts(...$assert);
        }
    }

    public function test_hint_chains_are_valid(): void
    {
        $result = self::getContainer()->getByType(Analyser::class)
            ->analyse([__DIR__.'/Fixtures/optimizer-hints.php'], debug: true);

        $this->assertNoErrors($result->getErrors());
    }

    public function test_hint_rejects_non_string_arguments(): void
    {
        $result = self::getContainer()->getByType(Analyser::class)
            ->analyse([__DIR__.'/Fixtures/invalid-optimizer-hints.php'], debug: true);

        $errors = $result->getErrors();
        $this->assertCount(4, $errors);

        foreach ($errors as $error) {
            $this->assertSame('argument.type', $error->getIdentifier());
            $this->assertStringContainsString('expects string', $error->getMessage());
        }
    }
}
