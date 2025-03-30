<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class ShallowTypeResolver
{
    public function __construct(
        private IndexContract $index,
    )
    {
    }

    public function resolve(Type $type): Type
    {
        return (new TypeWalker)->map(
            $type,
            $this->doResolve(...),
            function (Type $t) {
                $nodes = $t->nodes();
                /*
                 * When mapping function type, we don't want to affect arguments of the function types, just the return type.
                 */
                if ($t instanceof FunctionType) {
                    return [];
                }

                return $nodes;
            },
        );
    }

    private function doResolve(Type $type): Type
    {
        return match (true) {
            $type instanceof SelfType => $type,
            $type instanceof PropertyFetchReferenceType => $this->resolvePropertyFetchReferenceType($type),
            $type instanceof MethodCallReferenceType => $this->resolveMethodCallReferenceType($type),
            $type instanceof StaticMethodCallReferenceType => $this->resolveStaticMethodCallReferenceType($type),
            default => $type,
        };
    }

    private function resolvePropertyFetchReferenceType(PropertyFetchReferenceType $type): Type
    {
        $callee = $this->resolve($type->object);
        if (! $callee instanceof ObjectType) {
            return new UnknownType('fetching a property on a non-object');
        }

        $definition = $this->index->getClass($callee->name);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$callee->name);
        }

        $propertyDefinition = $definition->getData()->properties[$type->propertyName] ?? null;
        if (! $propertyDefinition) {
            return new UnknownType("property [{$type->propertyName}] is not found on object [{$callee->name}]");
        }

        $propertyType = $propertyDefinition->type ?: $propertyDefinition->defaultType;
        if ($propertyType instanceof TemplateType) {
            $propertyType = $propertyType->is;
        }

        if (! $propertyType) {
            return new MixedType;
        }

        return $propertyType;
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type): Type
    {
        $callee = $this->resolve($type->callee);
        if (! $callee instanceof ObjectType) {
            return new UnknownType('calling a method on a non-object');
        }

        $definition = $this->index->getClass($callee->name);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$callee->name);
        }

        $methodDefinition = $definition->getMethod($type->methodName);
        if (! $methodDefinition) {
            return new UnknownType("method [{$type->methodName}] is not found on object [{$callee->name}]");
        }

        return $this->resolveStaticBinding($definition->getData(), $methodDefinition, $methodDefinition->type->returnType);
    }

    private function resolveStaticMethodCallReferenceType(StaticMethodCallReferenceType $type): Type
    {
        $class = $type->callee instanceof Type
            ? $this->resolve($type->callee)
            : $type->callee;

        if ($class instanceof LiteralStringType) {
            $class = $class->value;
        }

        if (! is_string($class)) {
            return new UnknownType;
        }

        $definition = $this->index->getClass($class);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$class);
        }

        $methodDefinition = $definition->getMethod($type->methodName);
        if (! $methodDefinition) {
            return new UnknownType("method [{$type->methodName}] is not found on object [{$class}]");
        }

        return $this->resolveStaticBinding($definition->getData(), $methodDefinition, $methodDefinition->type->returnType);
    }

    /**
     * When passed an object with `self`, `static`, or `parent` name resolves the correct class name.
     */
    private function resolveStaticBinding(
        ClassDefinition $classDefinitionData,
        FunctionLikeDefinition $methodDefinition,
        Type $type,
    ): Type
    {
        if (! $type instanceof ObjectType) {
            return $type;
        }

        return match ($type->name) {
            StaticReference::SELF => tap(clone $type, fn ($t) => $t->name = $methodDefinition->definingClassName ?: $classDefinitionData->name),
            StaticReference::STATIC => tap(clone $type, fn ($t) => $t->name = $classDefinitionData->name),
            StaticReference::PARENT => $classDefinitionData->parentFqn
                ? tap(clone $type, fn ($t) => $t->name = $classDefinitionData->parentFqn)
                : new UnknownType,
            default => $type,
        };
    }
}
