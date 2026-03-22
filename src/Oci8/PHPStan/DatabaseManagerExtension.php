<?php

namespace Yajra\Oci8\PHPStan;

use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;

class DatabaseManagerExtension implements MethodsClassReflectionExtension
{
    private const SUPPORTED_METHODS = [
        'getSchema',
        'setSchema',
        'setSessionVars',
        'getSequence',
        'getTrigger',
        'serverVersion',
        'isVersionAbove',
        'isVersionAboveOrEqual',
        'isVersionBelow',
        'isVersionBelowOrEqual',
        'setDateFormat',
        'getDateFormat',
        'executeFunction',
        'executeProcedure',
        'executeProcedureWithCursor',
        'createSqlFromProcedure',
        'createStatementFromProcedure',
        'createStatementFromFunction',
        'getSchemaPrefix',
        'setSchemaPrefix',
        'getMaxLength',
        'setMaxLength',
        'getSchemaState',
        'addBindingsToStatement',
        'useCaseInsensitiveSession',
        'useCaseSensitiveSession',
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->getName() !== 'Illuminate\Database\DatabaseManager') {
            return false;
        }

        if (! in_array($methodName, self::SUPPORTED_METHODS, true)) {
            return false;
        }

        // Only add these methods if the Oracle connection class exists
        try {
            $this->getOci8ConnectionClass();

            return true;
        } catch (ClassNotFoundException $e) {
            return false;
        }
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        try {
            $oci8ConnectionClass = $this->getOci8ConnectionClass();

            if ($oci8ConnectionClass->hasNativeMethod($methodName)) {
                return $oci8ConnectionClass->getNativeMethod($methodName);
            }
        } catch (ClassNotFoundException $e) {
            throw new ShouldNotHappenException('Yajra\Oci8\Oci8Connection class not found');
        }

        throw new ShouldNotHappenException("Method {$methodName} not found on Oci8Connection");
    }

    private function getOci8ConnectionClass(): ClassReflection
    {
        return $this->reflectionProvider->getClass('Yajra\Oci8\Oci8Connection');
    }
}
