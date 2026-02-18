<?php

namespace Yajra\Oci8\PHPStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;

class DatabaseManagerExtension implements MethodsClassReflectionExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->getName() !== 'Illuminate\Database\DatabaseManager') {
            return false;
        }

        if (! in_array($methodName, ['executeProcedure', 'executeProcedureWithCursor', 'executeFunction', 'setDateFormat'])) {
            return false;
        }

        // Only add these methods if the Oracle connection class exists
        try {
            $this->reflectionProvider->getClass('Yajra\Oci8\Oci8Connection');

            return true;
        } catch (\PHPStan\Broker\ClassNotFoundException $e) {
            return false;
        }
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        try {
            $oci8ConnectionClass = $this->reflectionProvider->getClass('Yajra\Oci8\Oci8Connection');

            if ($oci8ConnectionClass->hasNativeMethod($methodName)) {
                return $oci8ConnectionClass->getNativeMethod($methodName);
            }
        } catch (\PHPStan\Broker\ClassNotFoundException $e) {
            throw new ShouldNotHappenException('Yajra\Oci8\Oci8Connection class not found');
        }

        throw new ShouldNotHappenException("Method {$methodName} not found on Oci8Connection");
    }
}
