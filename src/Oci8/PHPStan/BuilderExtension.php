<?php

namespace Yajra\Oci8\PHPStan;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Larastan\Larastan\Methods\BuilderHelper;
use Larastan\Larastan\Reflection\EloquentBuilderMethodReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Yajra\Oci8\Query\OracleBuilder;

class BuilderExtension implements MethodsClassReflectionExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private BuilderHelper $builderHelper
    ) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $methodName === 'hint'
            && ($classReflection->is(Builder::class)
                || $classReflection->is(EloquentBuilder::class)
                || $classReflection->is(Model::class));
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->reflectionProvider->getClass(OracleBuilder::class)->getNativeMethod('hint');
        $variant = $method->getVariants()[0];
        $returnType = new ThisType($classReflection);

        if ($classReflection->is(Model::class)) {
            $modelType = new ObjectType($classReflection->getName());
            $builderName = $this->builderHelper->determineBuilderName($classReflection->getName());
            $classReflection = $this->reflectionProvider->getClass($builderName)->withTypes([$modelType]);
            $returnType = new GenericObjectType($builderName, [$modelType]);
        }

        return new EloquentBuilderMethodReflection(
            $methodName,
            $classReflection,
            $variant->getParameters(),
            $returnType,
            $variant->isVariadic(),
        );
    }
}
