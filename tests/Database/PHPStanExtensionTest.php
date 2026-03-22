<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\PHPStan\DatabaseFacadeExtension;
use Yajra\Oci8\PHPStan\DatabaseManagerExtension;

class PHPStanExtensionTest extends TestCase
{
    public function test_database_facade_extension_supported_methods_match_non_extended_oci8_connection_methods(): void
    {
        $this->assertSame(
            $this->nonExtendedOci8ConnectionMethods(),
            $this->supportedMethods(DatabaseFacadeExtension::class)
        );
    }

    public function test_database_manager_extension_supported_methods_match_non_extended_oci8_connection_methods(): void
    {
        $this->assertSame(
            $this->nonExtendedOci8ConnectionMethods(),
            $this->supportedMethods(DatabaseManagerExtension::class)
        );
    }

    public function test_supported_methods_do_not_include_inherited_connection_overrides(): void
    {
        $supportedMethods = $this->supportedMethods(DatabaseFacadeExtension::class);

        $this->assertSame(
            $this->supportedMethods(DatabaseManagerExtension::class),
            $supportedMethods
        );

        foreach ($this->extendedOci8ConnectionMethods() as $methodName) {
            $this->assertNotContains($methodName, $supportedMethods);
        }
    }

    /**
     * @return list<string>
     */
    private function nonExtendedOci8ConnectionMethods(): array
    {
        $connectionReflection = new \ReflectionClass(Oci8Connection::class);
        $parentReflection = new \ReflectionClass(Connection::class);

        $methods = [];

        foreach ($connectionReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== Oci8Connection::class) {
                continue;
            }

            if ($parentReflection->hasMethod($method->getName())) {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }

    /**
     * @return list<string>
     */
    private function extendedOci8ConnectionMethods(): array
    {
        $connectionReflection = new \ReflectionClass(Oci8Connection::class);
        $parentReflection = new \ReflectionClass(Connection::class);

        $methods = [];

        foreach ($connectionReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== Oci8Connection::class) {
                continue;
            }

            if (! $parentReflection->hasMethod($method->getName())) {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }

    /**
     * @return list<string>
     */
    private function supportedMethods(string $extensionClass): array
    {
        $reflection = new \ReflectionClass($extensionClass);
        $methods = $reflection->getConstant('SUPPORTED_METHODS');

        sort($methods);

        return $methods;
    }
}
