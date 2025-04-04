<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\ConstFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
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
    ) {}

    public function resolve(Scope $scope, Type $type): Type
    {
        return (new TypeWalker)->map(
            $type,
            fn ($t) => $this->doResolve($scope, $t),
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

    private function doResolve(Scope $scope, Type $type): Type
    {
        return match (true) {
            $type instanceof SelfType => $type,
            $type instanceof TemplateType => $type->is ?: new UnknownType,
            $type instanceof PropertyFetchReferenceType => $this->resolvePropertyFetchReferenceType($scope, $type),
            $type instanceof MethodCallReferenceType => $this->resolveMethodCallReferenceType($scope, $type),
            $type instanceof StaticMethodCallReferenceType => $this->resolveStaticMethodCallReferenceType($scope, $type),
            $type instanceof NewCallReferenceType => $this->resolveNewCallReferenceType($scope, $type),
            $type instanceof ConstFetchReferenceType => $this->resolveConstFetchReferenceType($scope, $type),
            $type instanceof CallableCallReferenceType => $this->resolveCallableCallReferenceType($scope, $type),
            $type instanceof AbstractReferenceType => new UnknownType($type::class.' reference type is not handled '.$type->toString()),
            default => $type,
        };
    }

    private function resolvePropertyFetchReferenceType(Scope $scope, PropertyFetchReferenceType $type): Type
    {
        $callee = $this->resolve($scope, $type->object);
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

    private function resolveMethodCallReferenceType(Scope $scope, MethodCallReferenceType $type): Type
    {
        $callee = $this->resolve($scope, $type->callee);
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

    private function resolveNewCallReferenceType(Scope $scope, NewCallReferenceType $type): Type
    {
        $class = $type->name instanceof Type
            ? $this->resolve($scope, $type->name)
            : $type->name;

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

        return new ObjectType($class);
    }

    private function resolveConstFetchReferenceType(Scope $scope, ConstFetchReferenceType $type): Type
    {
        $callee = $type->callee;

        if ($type->callee instanceof StaticReference) {
            $contextualCalleeName = match ($type->callee->keyword) {
                StaticReference::SELF => $scope->context->functionDefinition?->definingClassName,
                StaticReference::STATIC => $scope->context->classDefinition?->name,
                StaticReference::PARENT => $scope->context->classDefinition?->parentFqn,
            };

            // This can only happen if any of static reserved keyword used in non-class context – hence considering not possible for now.
            if (! $contextualCalleeName) {
                return new UnknownType("Cannot properly analyze [{$type->toString()}] reference type as static keyword used in non-class context, or current class scope has no parent.");
            }

            $callee = $contextualCalleeName;
        }

        return (new ConstFetchTypeGetter)($scope, $callee, $type->constName);
    }

    private function resolveCallableCallReferenceType(Scope $scope, CallableCallReferenceType $type): Type
    {
        $callee = $this->resolve($scope, $type->callee);

        $function = match ($callee::class) {
            CallableStringType::class => $callee->name,
            LiteralStringType::class => $callee->value,
            ObjectType::class => $callee,
            default => null,
        };

        if (! $function) {
            return new UnknownType;
        }

        $functionDefinition = is_string($function)
            ? $this->index->getFunction($function)
            : $this->index->getClass($function->name)->getMethod('__invoke');

        if (! $functionDefinition) {
            return new UnknownType('cannot find a function definition of '.$callee->toString());
        }

        return $functionDefinition->type->returnType;
    }

    private function resolveStaticMethodCallReferenceType(Scope $scope, StaticMethodCallReferenceType $type): Type
    {
        $class = $type->callee instanceof Type
            ? $this->resolve($scope, $type->callee)
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
    ): Type {
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
