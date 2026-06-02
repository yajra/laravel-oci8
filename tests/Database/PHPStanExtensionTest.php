<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Connection;
use Larastan\Larastan\Reflection\StaticMethodReflection;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Testing\PHPStanTestCase;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\PHPStan\DatabaseFacadeExtension;
use Yajra\Oci8\PHPStan\DatabaseManagerExtension;

class PHPStanExtensionTest extends PHPStanTestCase
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

    public function test_database_facade_extension_resolves_actual_oci8_connection_methods(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $extension = new DatabaseFacadeExtension($reflectionProvider);
        $facadeReflection = $reflectionProvider->getClass('Illuminate\Support\Facades\DB');

        foreach ($this->nonExtendedOci8ConnectionMethods() as $methodName) {
            $this->assertTrue($extension->hasMethod($facadeReflection, $methodName));

            $methodReflection = $extension->getMethod($facadeReflection, $methodName);

            $this->assertInstanceOf(StaticMethodReflection::class, $methodReflection);
            $this->assertSame($methodName, $methodReflection->getName());
            $this->assertTrue($methodReflection->isStatic());
            $this->assertTrue($methodReflection->isPublic());
        }
    }

    public function test_database_manager_extension_resolves_actual_oci8_connection_methods(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $extension = new DatabaseManagerExtension($reflectionProvider);
        $managerReflection = $reflectionProvider->getClass('Illuminate\Database\DatabaseManager');

        foreach ($this->nonExtendedOci8ConnectionMethods() as $methodName) {
            $this->assertTrue($extension->hasMethod($managerReflection, $methodName));

            $methodReflection = $extension->getMethod($managerReflection, $methodName);

            $this->assertInstanceOf(MethodReflection::class, $methodReflection);
            $this->assertSame($methodName, $methodReflection->getName());
            $this->assertFalse($methodReflection->isStatic());
            $this->assertTrue($methodReflection->isPublic());
        }
    }

    public function test_extensions_reject_wrong_target_classes_and_unsupported_methods(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $facadeExtension = new DatabaseFacadeExtension($reflectionProvider);
        $managerExtension = new DatabaseManagerExtension($reflectionProvider);

        $facadeReflection = $reflectionProvider->getClass('Illuminate\Support\Facades\DB');
        $managerReflection = $reflectionProvider->getClass('Illuminate\Database\DatabaseManager');
        $connectionReflection = $reflectionProvider->getClass(Connection::class);

        $this->assertFalse($facadeExtension->hasMethod($connectionReflection, 'getSchema'));
        $this->assertFalse($facadeExtension->hasMethod($facadeReflection, 'unsupportedOci8Method'));
        $this->assertFalse($managerExtension->hasMethod($connectionReflection, 'getSchema'));
        $this->assertFalse($managerExtension->hasMethod($managerReflection, 'unsupportedOci8Method'));
    }

    public function test_extensions_reject_supported_methods_when_oci8_connection_class_is_missing(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $facadeReflection = $reflectionProvider->getClass('Illuminate\Support\Facades\DB');
        $managerReflection = $reflectionProvider->getClass('Illuminate\Database\DatabaseManager');

        $missingConnectionProvider = $this->createMock(ReflectionProvider::class);
        $missingConnectionProvider
            ->method('getClass')
            ->with(Oci8Connection::class)
            ->willThrowException(new ClassNotFoundException(Oci8Connection::class));

        $this->assertFalse((new DatabaseFacadeExtension($missingConnectionProvider))->hasMethod($facadeReflection, 'getSchema'));
        $this->assertFalse((new DatabaseManagerExtension($missingConnectionProvider))->hasMethod($managerReflection, 'getSchema'));
    }

    public function test_get_method_throws_when_oci8_connection_class_is_missing(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $facadeReflection = $reflectionProvider->getClass('Illuminate\Support\Facades\DB');

        $missingConnectionProvider = $this->createMock(ReflectionProvider::class);
        $missingConnectionProvider
            ->method('getClass')
            ->with(Oci8Connection::class)
            ->willThrowException(new ClassNotFoundException(Oci8Connection::class));

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('Yajra\Oci8\Oci8Connection class not found');

        (new DatabaseFacadeExtension($missingConnectionProvider))->getMethod($facadeReflection, 'getSchema');
    }

    public function test_database_manager_get_method_throws_when_oci8_connection_class_is_missing(): void
    {
        $reflectionProvider = $this->createReflectionProvider();
        $managerReflection = $reflectionProvider->getClass('Illuminate\Database\DatabaseManager');

        $missingConnectionProvider = $this->createMock(ReflectionProvider::class);
        $missingConnectionProvider
            ->method('getClass')
            ->with(Oci8Connection::class)
            ->willThrowException(new ClassNotFoundException(Oci8Connection::class));

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('Yajra\Oci8\Oci8Connection class not found');

        (new DatabaseManagerExtension($missingConnectionProvider))->getMethod($managerReflection, 'getSchema');
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
