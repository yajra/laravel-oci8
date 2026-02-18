<?php

namespace Yajra\Oci8\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VersionCompareTest extends TestCase
{
    public static function oracleVersionProvider(): array
    {
        return [
            ['12c',  '',  '>', true],
            ['12c',  '11g',  '>', true],
            ['12c',  '12c',  '>', false],
            ['12c',  '19c',  '>', false],
            ['12c',  '21c',  '>', false],
            ['12c',  '23ai', '>', false],
            ['12c',  '26ai', '>', false],

            ['12c', '', '<=', false],
            ['12c', '11g', '<=', false],
            ['12c', '12c', '<=', true],
            ['12c', '19c', '<=', true],
            ['12c', '21c', '<=', true],
            ['12c', '23ai', '<=', true],
            ['12c', '26ai', '<=', true],
        ];
    }

    #[Test]
    #[DataProvider('oracleVersionProvider')]
    public function it_correctly_compares_oracle_versions(string $v1, string $v2, string $operator, bool $expected)
    {
        $result = version_compare($v1, $v2, $operator);

        $this->assertEquals(
            $expected,
            $result,
            "Failed asserting that $v1 compared to $v2 using operator $operator yields $expected"
        );
    }
}
