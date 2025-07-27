<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
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
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    ) {}

    public static function getInstance(): static
    {
        return app(static::class);
    }

    public function resolve(Scope $scope, Type $type): Type
    {
        $originalType = $type;

        $resolvedType = RecursionGuard::run(
            $type,
            fn () => (new TypeWalker)->map($type, fn (Type $t) => $this->doResolve($t, $type, $scope)),
            onInfiniteRecursion: fn () => new UnknownType('really bad self reference'),
        );

        // Type finalization: removing duplicates from union + unpacking array items.
        $finalizedResolvedType = (new TypeWalker)->map($resolvedType, function (Type $t) {
            if ($t instanceof Union) {
                return TypeHelper::mergeTypes(...$t->types);
            }
            if ($t instanceof KeyedArrayType) {
                return TypeHelper::unpackIfArray($t);
            }

            return $t;
        });

        return $finalizedResolvedType->setOriginal($originalType);
    }

    private function doResolve(Type $t, Type $type, Scope $scope): Type
    {
        $resolved = match ($t::class) {
            ConstFetchReferenceType::class => $this->resolveConstFetchReferenceType($scope, $t),
            MethodCallReferenceType::class => $this->resolveMethodCallReferenceType($scope, $t),
            StaticMethodCallReferenceType::class => $this->resolveStaticMethodCallReferenceType($scope, $t),
            CallableCallReferenceType::class => $this->resolveCallableCallReferenceType($scope, $t),
            NewCallReferenceType::class => $this->resolveNewCallReferenceType($scope, $t),
            PropertyFetchReferenceType::class => $this->resolvePropertyFetchReferenceType($scope, $t),
            default => null,
        };

        if (! $resolved) {
            return $t;
        }

        if ($resolved === $type || $resolved === $t) {
            return new UnknownType('self reference');
        }

        return $this->resolve($scope, $resolved);
    }

    private function resolveConstFetchReferenceType(Scope $scope, ConstFetchReferenceType $type): Type
    {
        $contextualCalleeName = $type->callee;

        if ($contextualCalleeName instanceof StaticReference) {
            $contextualCalleeName = static::resolveClassName($scope, $contextualCalleeName->keyword);

            // This can only happen if any of static reserved keyword used in non-class context – hence considering not possible for now.
            if (! $contextualCalleeName) {
                return new UnknownType("Cannot properly analyze [{$type->toString()}] reference type as static keyword used in non-class context, or current class scope has no parent.");
            }
        }

        return (new ConstFetchTypeGetter)($scope, $contextualCalleeName, $type->constName);
    }

    /**
     * Prepares the type of the value a method will be called on or a property will be fetched on. This includes
     * resolving the reference type and using the lower bound of if the callee is a template type.
     * Possible todo here is to add Union support as now only direct abstract reference will be resolved which is fine for now.
     */
    private function resolveAndNormalizeCallee(Scope $scope, Type $callee): Type
    {
        $resolved = $callee instanceof AbstractReferenceType
            ? $this->resolve($scope, $callee)
            : $callee;

        if ($resolved instanceof TemplateType && $resolved->is) {
            return $resolved->is;
        }

        return $resolved;
    }

    private function resolveMethodCallReferenceType(Scope $scope, MethodCallReferenceType $type): Type
    {
        // (#self).listTableDetails()
        // (#Doctrine\DBAL\Schema\Table).listTableDetails()
        // (#TName).listTableDetails()

        $calleeType = $this->resolveAndNormalizeCallee($scope, $type->callee);
        $type->callee = $calleeType; // @todo stop mutating `$type` use `$calleeType` instead.

        $type->arguments = array_map(
            fn ($t) => $this->resolve($scope, $t),
            $type->arguments,
        );

        $classDefinition = $calleeType instanceof ObjectType
            ? $this->index->getClass($calleeType->name)
            : null;

        if (! ($calleeType instanceof TemplateType) && $returnType = Context::getInstance()->extensionsBroker->getAnyMethodReturnType(new AnyMethodCallEvent(
            instance: $calleeType,
            name: $type->methodName,
            scope: $scope,
            arguments: $type->arguments,
            methodDefiningClassName: $classDefinition
                ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index)
                : ($calleeType instanceof ObjectType ? $calleeType->name : null),
        ))) {
            return $returnType;
        }

        if (! $calleeType instanceof ObjectType) {
            return new UnknownType;
        }

        if ($returnType = Context::getInstance()->extensionsBroker->getMethodReturnType($event = new MethodCallEvent(
            instance: $calleeType,
            name: $type->methodName,
            scope: $scope,
            arguments: $type->arguments,
            methodDefiningClassName: $classDefinition ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index) : $calleeType->name,
        ))) {
            return $returnType;
        }

        if (! $classDefinition) {
            return new UnknownType;
        }

        if (! $methodDefinition = $calleeType->getMethodDefinition($type->methodName, $scope)) {
            return new UnknownType("Cannot get a method type [$type->methodName] on type [$calleeType->name]");
        }

        $resultingType = $this->getFunctionCallResult($methodDefinition, $type->arguments, $calleeType, $event);

        if ($calleeType instanceof SelfType) {
            return $resultingType;
        }

        // @todo resolve template type?
        return $resultingType instanceof TemplateType
            ? ($resultingType->is ?: new UnknownType)
            : $resultingType;
    }

    private function resolveStaticMethodCallReferenceType(Scope $scope, StaticMethodCallReferenceType $type): Type
    {
        // (#self).listTableDetails()
        // (#Doctrine\DBAL\Schema\Table).listTableDetails()
        // (#TName).listTableDetails()

        $type->arguments = array_map(
            // @todo: fix resolving arguments when deep arg is reference
            fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
            $type->arguments,
        );

        $calleeName = $type->callee;

        if ($calleeName instanceof Type) {
            $calleeType = $this->resolve($scope, $calleeName);

            if ($calleeType instanceof LiteralStringType) {
                $calleeName = $calleeType->value;
            }

            if (! is_string($calleeName)) {
                return new UnknownType;
            }
        }

        $contextualClassName = $this->resolveClassName($scope, $calleeName);
        if (! $contextualClassName) {
            return new UnknownType;
        }
        $calleeName = $contextualClassName;

        $isStaticCall = ! in_array($type->callee, StaticReference::KEYWORDS)
            || (in_array($type->callee, StaticReference::KEYWORDS) && $scope->context->functionDefinition?->isStatic);

        // Assuming callee here can be only string of known name. Reality is more complex than
        // that, but it is fine for now.

        // Attempting extensions broker before potentially giving up on type inference
        if ($isStaticCall && $returnType = Context::getInstance()->extensionsBroker->getStaticMethodReturnType(new StaticMethodCallEvent(
            callee: $calleeName,
            name: $type->methodName,
            scope: $scope,
            arguments: $type->arguments,
        ))) {
            return $returnType;
        }

        // Attempting extensions broker before potentially giving up on type inference
        if (! $isStaticCall && $scope->context->classDefinition) {
            $definingMethodName = ($definingClass = $scope->index->getClass($contextualClassName))
                ? $definingClass->getMethodDefiningClassName($type->methodName, $scope->index)
                : $contextualClassName;

            $returnType = Context::getInstance()->extensionsBroker->getMethodReturnType($e = new MethodCallEvent(
                instance: $i = new ObjectType($scope->context->classDefinition->name),
                name: $type->methodName,
                scope: $scope,
                arguments: $type->arguments,
                methodDefiningClassName: $definingMethodName,
            ));

            if ($returnType) {
                return $returnType;
            }
        }

        if (! $calleeDefinition = $this->index->getClass($calleeName)) {
            return new UnknownType;
        }

        if (! $methodDefinition = $calleeDefinition->getMethodDefinition($type->methodName, $scope)) {
            return new UnknownType("Cannot get a method type [$type->methodName] on type [$calleeName]");
        }

        return $this->getFunctionCallResult($methodDefinition, $type->arguments);
    }

    private function resolveCallableCallReferenceType(Scope $scope, CallableCallReferenceType $type): Type
    {
        $callee = $this->resolve($scope, $type->callee);
        $callee = $callee instanceof TemplateType ? $callee->is : $callee;

        if ($callee instanceof CallableStringType) {
            $analyzedType = clone $type;

            $analyzedType->arguments = array_map(
                // @todo: fix resolving arguments when deep arg is reference
                fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
                $type->arguments,
            );

            $returnType = Context::getInstance()->extensionsBroker->getFunctionReturnType(new FunctionCallEvent(
                name: $callee->name,
                scope: $scope,
                arguments: $analyzedType->arguments,
            ));

            if ($returnType) {
                return $returnType;
            }
        }

        if ($callee instanceof ObjectType) {
            return $this->resolve(
                $scope,
                new MethodCallReferenceType($callee, '__invoke', $type->arguments),
            );
        }

        $calleeType = $callee instanceof CallableStringType
            ? $this->index->getFunctionDefinition($callee->name)
            : $callee;

        if (! $calleeType) {
            // Callee cannot be resolved from index.
            return new UnknownType;
        }

        if ($calleeType instanceof FunctionType) { // When resolving into a closure.
            $calleeType = new FunctionLikeDefinition($calleeType);
        }

        // @todo: callee now can be either in index or not, add support for other cases.
        if (! $calleeType instanceof FunctionLikeDefinition) {
            // Callee cannot be resolved.
            return new UnknownType;
        }

        return $this->getFunctionCallResult($calleeType, $type->arguments);
    }

    private function resolveNewCallReferenceType(Scope $scope, NewCallReferenceType $type): Type
    {
        if ($type->name instanceof Type) {
            $resolvedNameType = $this->resolve($scope, $type->name);

            if ($resolvedNameType instanceof LiteralStringType) {
                $type->name = $resolvedNameType->value;
            } else {
                return new UnknownType;
            }
        }

        $contextualClassName = $this->resolveClassName($scope, $type->name);
        if (! $contextualClassName) {
            return new UnknownType;
        }
        $type->name = $contextualClassName;

        if (! $classDefinition = $this->index->getClass($type->name)) {
            /*
             * Usually in this case we want to return UnknownType. But we certainly know that using `new` will produce
             * an object of a type being created.
             */
            return new ObjectType($type->name);
        }

        $type->arguments = array_map(
            fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
            $type->arguments,
        );

        $typeBeingConstructed = ! $classDefinition->templateTypes
            ? new ObjectType($type->name)
            : new Generic($type->name, array_map(fn () => new MixedType, $classDefinition->templateTypes));

        if (Context::getInstance()->extensionsBroker->getMethodReturnType(new MethodCallEvent(
            instance: $typeBeingConstructed,
            name: '__construct',
            scope: $scope,
            arguments: $type->arguments,
            methodDefiningClassName: $type->name,
        )) instanceof VoidType) {
            return $typeBeingConstructed;
        }

        if (! $classDefinition->templateTypes) {
            return new ObjectType($type->name);
        }

        $propertyDefaultTemplateTypes = collect($classDefinition->properties)
            ->mapWithKeys(fn (ClassPropertyDefinition $definition) => $definition->type instanceof TemplateType ? [
                $definition->type->name => $definition->defaultType,
            ] : [])
            ->filter();

        $constructorDefinition = $classDefinition->getMethodDefinition('__construct', $scope);

        $inferredConstructorParamTemplates = (new TemplateTypesSolver)->getClassConstructorContextTemplates(
            $classDefinition,
            $constructorDefinition,
            $type->arguments,
        );

        $inferredTemplates = collect()
            ->merge($propertyDefaultTemplateTypes)
            ->merge($inferredConstructorParamTemplates);

        $resultingTemplatesMap = collect($classDefinition->templateTypes)
            ->map(fn (TemplateType $t) => $inferredTemplates->get($t->name, new UnknownType))
            ->toArray();

        if ($constructorDefinition?->selfOutType instanceof Generic) {
            foreach ($constructorDefinition->selfOutType->templateTypes as $index => $genericSelfOutTypePart) {
                if ($genericSelfOutTypePart instanceof TemplatePlaceholderType) {
                    continue;
                }

                $resultingTemplatesMap[$index] = (new TypeWalker)->map(
                    $genericSelfOutTypePart,
                    fn ($t) => $t instanceof TemplateType && array_key_exists($t->name, $inferredConstructorParamTemplates)
                        ? $inferredConstructorParamTemplates[$t->name]
                        : $t,
                );
            }
        }

        return new Generic($classDefinition->name, $resultingTemplatesMap);
    }

    private function resolvePropertyFetchReferenceType(Scope $scope, PropertyFetchReferenceType $type): Type
    {
        $objectType = $this->resolveAndNormalizeCallee($scope, $type->object);

        if (! $objectType instanceof ObjectType) {
            return new UnknownType;
        }

        if ($propertyType = Context::getInstance()->extensionsBroker->getPropertyType(new PropertyFetchEvent(
            instance: $objectType,
            name: $type->propertyName,
            scope: $scope,
        ))) {
            return $propertyType;
        }

        $classDefinition = $this->index->getClass($objectType->name);

        if (! $classDefinition) {
            return new UnknownType("Cannot get property [$type->propertyName] type on [$objectType->name]");
        }

        $propertyType = $objectType->getPropertyType($type->propertyName, $scope);

        if ($objectType instanceof SelfType) {
            return $propertyType;
        }

        // @todo resolve template type?
        return $propertyType instanceof TemplateType
            ? ($propertyType->is ?: new UnknownType)
            : $propertyType;
    }

    /**
     * @param  array<array-key, Type>  $arguments
     */
    private function getFunctionCallResult(
        FunctionLikeDefinition $callee,
        array $arguments,
        /* When this is a handling for method call */
        ObjectType|SelfType|null $calledOnType = null,
        ?MethodCallEvent $event = null,
    ): Type {
        $returnType = $callee->type->getReturnType();
        $isSelf = $returnType instanceof SelfType && $calledOnType;

        if ($returnType instanceof SelfType && $calledOnType) {
            $returnType = $calledOnType;
        }

        $instanceTemplates = $calledOnType && ($classDefinition = $this->index->getClass($calledOnType->name))
            ? (new TemplateTypesSolver)->getClassContextTemplates($calledOnType, $classDefinition)
            : [];

        $inferredTemplates = array_merge(
            $instanceTemplates,
            (new TemplateTypesSolver)->getFunctionContextTemplates($callee, $arguments)
        );

        $returnType = (new TypeWalker)->map($returnType, function (Type $t) use ($inferredTemplates) {
            return $t instanceof TemplateType && array_key_exists($t->name, $inferredTemplates)
                ? $inferredTemplates[$t->name]
                : $t;
        });

        foreach ($callee->sideEffects as $sideEffect) {
            if (
                $sideEffect instanceof SelfTemplateDefinition
                && $isSelf
                && $returnType instanceof Generic
                && $event
            ) {
                $sideEffect->apply($returnType, $event);
            }
        }

        return $returnType;
    }

    public static function resolveClassName(Scope $scope, string $name): ?string
    {
        if (! in_array($name, StaticReference::KEYWORDS)) {
            return $name;
        }

        return match ($name) {
            StaticReference::SELF => $scope->context->functionDefinition?->definingClassName,
            StaticReference::STATIC => $scope->context->classDefinition?->name,
            StaticReference::PARENT => $scope->context->classDefinition?->parentFqn,
        };
    }
}
