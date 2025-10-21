<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
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
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeTraverser;
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

        // Type finalization: removing duplicates from union, unpacking array items (inside `replace`), calling resolving extensions.
        $finalizedResolvedType = (new TypeWalker)->replace(
            $resolvedType,
            fn (Type $t) => $t instanceof Union ? TypeHelper::mergeTypes(...$t->types) : null,
        );

        return $this->resolveLateTypes($finalizedResolvedType->setOriginal($originalType), $originalType)->widen();
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
            LateResolvingType::class => $this->resolveLateTypeEarly($t),
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

    private function finalizeStatic(Type $type, Type $staticType): Type
    {
        return (new TypeWalker)->map($type, function (Type $t) use ($staticType) {
            if ($t instanceof Generic && $staticType instanceof ObjectType && $t->name === StaticReference::STATIC) {
                $t->name = $staticType->name;

                return $t;
            }

            if ($staticType instanceof ObjectType && $t instanceof ObjectType && $t->name === StaticReference::SELF) {
                $t->name = $staticType->name;

                return $t;
            }

            return $t instanceof ObjectType && $t->name === StaticReference::STATIC ? $staticType : $t;
        });
    }

    private function resolveLateTypeEarly(LateResolvingType $type): Type
    {
        if (! $type->isResolvable()) {
            return $type;
        }

        return $type->resolve();
    }

    private function resolveLateTypes(Type $type, Type $originalType): Type
    {
        $attributes = $type->attributes();

        $traverser = new TypeTraverser([
            new LateTypeResolvingTypeVisitor,
        ]);

        return $traverser
            ->traverse($type)
            ->mergeAttributes($attributes)
            ->setOriginal($originalType);
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

    private function resolveMethodCallReferenceType(Scope $scope, MethodCallReferenceType $type): Type
    {
        $calleeType = $this->resolveAndNormalizeCallee($scope, $type->callee);
        $type->callee = $calleeType; // @todo stop mutating `$type` use `$calleeType` instead.
        $arguments = new AutoResolvingArgumentTypeBag($scope, $type->arguments);

        $classDefinition = $calleeType instanceof ObjectType
            ? $this->index->getClass($calleeType->name)
            : null;

        if (! ($calleeType instanceof TemplateType) && $returnType = Context::getInstance()->extensionsBroker->getAnyMethodReturnType(new AnyMethodCallEvent(
            instance: $calleeType,
            name: $type->methodName,
            scope: $scope,
            arguments: $arguments,
            methodDefiningClassName: $classDefinition
                ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index)
                : ($calleeType instanceof ObjectType ? $calleeType->name : null),
        ))) {
            return $this->finalizeStatic($returnType, $calleeType);
        }

        if (! $calleeType instanceof ObjectType) {
            return new UnknownType;
        }

        if ($returnType = Context::getInstance()->extensionsBroker->getMethodReturnType(new MethodCallEvent(
            instance: $calleeType,
            name: $type->methodName,
            scope: $scope,
            arguments: $arguments,
            methodDefiningClassName: $classDefinition ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index) : $calleeType->name,
        ))) {
            return $this->finalizeStatic($returnType, $calleeType);
        }

        if (! $classDefinition) {
            return new UnknownType;
        }

        if (! $methodDefinition = $calleeType->getMethodDefinition($type->methodName, $scope)) {
            return new UnknownType("Cannot get a method type [$type->methodName] on type [$calleeType->name]");
        }

        $resultingType = $this->getFunctionCallResult($methodDefinition, new AutoResolvingArgumentTypeBag($scope, $type->arguments), $calleeType);

        if ($calleeType instanceof SelfType) {
            return $resultingType;
        }

        // @todo resolve template type?
        $resultingType = $resultingType instanceof TemplateType
            ? ($resultingType->is ?: new UnknownType)
            : $resultingType;

        return $this->finalizeStatic($resultingType, $calleeType);
    }

    private function resolveStaticMethodCallReferenceType(Scope $scope, StaticMethodCallReferenceType $type): Type
    {
        if (! $contextualClassName = $this->resolveContextualClassName($scope, $type->callee)) {
            return new UnknownType;
        }

        $arguments = new AutoResolvingArgumentTypeBag($scope, $type->arguments);

        $isStaticCall = ! in_array($type->callee, StaticReference::KEYWORDS)
            || (in_array($type->callee, StaticReference::KEYWORDS) && $scope->context->functionDefinition?->isStatic);

        // Assuming callee here can be only string of known name. Reality is more complex than
        // that, but it is fine for now.

        // Attempting extensions broker before potentially giving up on type inference
        if ($isStaticCall && $returnType = Context::getInstance()->extensionsBroker->getStaticMethodReturnType(new StaticMethodCallEvent(
            callee: $contextualClassName,
            name: $type->methodName,
            scope: $scope,
            arguments: $arguments,
        ))) {
            return $returnType;
        }

        // Attempting extensions broker before potentially giving up on type inference
        if (! $isStaticCall && $scope->context->classDefinition) {
            $definingMethodName = ($definingClass = $scope->index->getClass($contextualClassName))
                ? $definingClass->getMethodDefiningClassName($type->methodName, $scope->index)
                : $contextualClassName;

            $returnType = Context::getInstance()->extensionsBroker->getMethodReturnType(new MethodCallEvent(
                instance: new SelfType($scope->context->classDefinition->name),
                name: $type->methodName,
                scope: $scope,
                arguments: $arguments,
                methodDefiningClassName: $definingMethodName,
            ));

            if ($returnType) {
                return $returnType;
            }
        }

        if (! $calleeDefinition = $this->index->getClass($contextualClassName)) {
            return new UnknownType;
        }

        if (! $methodDefinition = $calleeDefinition->getMethodDefinition($type->methodName, $scope)) {
            return new UnknownType("Cannot get a method type [$type->methodName] on type [$contextualClassName]");
        }

        return $this->finalizeStatic(
            $this->getFunctionCallResult($methodDefinition, $arguments),
            new ObjectType($contextualClassName), // @todo Generic can be here.
        );
    }

    private function resolveCallableCallReferenceType(Scope $scope, CallableCallReferenceType $type): Type
    {
        $callee = $this->resolve($scope, $type->callee);
        $callee = $callee instanceof TemplateType ? $callee->is : $callee;

        $arguments = new AutoResolvingArgumentTypeBag($scope, $type->arguments);

        if ($callee instanceof CallableStringType) {
            $returnType = Context::getInstance()->extensionsBroker->getFunctionReturnType(new FunctionCallEvent(
                name: $callee->name,
                scope: $scope,
                arguments: $arguments,
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
            return new UnknownType;
        }

        if ($calleeType instanceof FunctionType) { // When resolving into a closure.
            $calleeType = new FunctionLikeDefinition($calleeType);
        }

        // @todo: callee now can be either in index or not, add support for other cases.
        if (! $calleeType instanceof FunctionLikeDefinition) {
            return new UnknownType;
        }

        return $this->getFunctionCallResult($calleeType, $arguments);
    }

    private function resolveNewCallReferenceType(Scope $scope, NewCallReferenceType $type): Type
    {
        if (! $contextualClassName = $this->resolveContextualClassName($scope, $type->name)) {
            return new UnknownType;
        }

        $arguments = new AutoResolvingArgumentTypeBag($scope, $type->arguments);

        if (! $classDefinition = $this->index->getClass($contextualClassName)) {
            /*
             * Usually in this case we want to return UnknownType. But we certainly know that using `new` will produce
             * an object of a type being created.
             */
            return new ObjectType($contextualClassName);
        }

        $typeBeingConstructed = ! $classDefinition->templateTypes
            ? new ObjectType($contextualClassName)
            : new Generic($contextualClassName, array_map(fn () => new MixedType, $classDefinition->templateTypes));

        if (Context::getInstance()->extensionsBroker->getMethodReturnType(new MethodCallEvent(
            instance: $typeBeingConstructed,
            name: '__construct',
            scope: $scope,
            arguments: $arguments,
            methodDefiningClassName: $contextualClassName,
        )) instanceof VoidType) {
            return $typeBeingConstructed;
        }

        if (! $classDefinition->templateTypes) {
            return new ObjectType($contextualClassName);
        }

        $propertyDefaultTemplateTypes = (new TemplateTypesSolver)
            ->inferTemplatesFromPropertyDefaults(
                $classDefinition->templateTypes,
                $classDefinition->properties,
            );

        $constructorDefinition = $classDefinition->getMethodDefinition('__construct', $scope);

        $templatesMap = (new TemplateTypesSolver)
            ->getClassConstructorContextTemplates(
                $classDefinition,
                $constructorDefinition,
                new AutoResolvingArgumentTypeBag($scope, $type->arguments),
            )
            ->prepend($propertyDefaultTemplateTypes);

        $resultingTemplatesMap = (new TemplateTypesSolver)
            ->getGenericCreationTemplatesWithDefaults($classDefinition->templateTypes, $templatesMap);

        $resultingTemplatesMap = $this->applySelfOutType(
            $resultingTemplatesMap,
            $constructorDefinition?->getSelfOutType(),
            $templatesMap,
        );

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
     * Prepares the type of the value a method will be called on or a property will be fetched on. This includes
     * resolving the reference type and using the lower bound of if the callee is a template type.
     * Possible todo here is to add Union support as now only direct abstract reference will be resolved which is fine for now.
     */
    private function resolveAndNormalizeCallee(Scope $scope, Type $callee): Type
    {
        $resolved = ($callee instanceof AbstractReferenceType || $callee instanceof LateResolvingType)
            ? $this->resolve($scope, $callee)
            : $callee;

        if ($resolved instanceof TemplateType && $resolved->is) {
            return $resolved->is;
        }

        return $resolved;
    }

    /**
     * Resolves the name of the type for static context (when creating new objects and calling static methods):
     * For example: new static/new Object/static::X/Object::X/('Object')::X
     */
    private function resolveContextualClassName(Scope $scope, Type|string $type): ?string
    {
        if ($type instanceof Type) {
            $resolvedNameType = $this->resolve($scope, $type);

            if ($resolvedNameType instanceof LiteralStringType) {
                return $resolvedNameType->value;
            }

            return null;
        }

        return $this->resolveClassName($scope, $type);
    }

    /**
     * @param  Type[]  $resultingTemplatesMap
     * @return Type[]
     */
    private function applySelfOutType(array $resultingTemplatesMap, ?Type $selfOutType, TemplatesMap $inferredTemplates): array
    {
        if (! $selfOutType instanceof Generic) {
            return $resultingTemplatesMap;
        }

        foreach ($selfOutType->templateTypes as $index => $genericSelfOutTypePart) {
            if ($genericSelfOutTypePart instanceof TemplatePlaceholderType) {
                continue;
            }

            $resultingTemplatesMap[$index] = (new TypeWalker)->map(
                $genericSelfOutTypePart,
                fn ($t) => $t instanceof TemplateType ? $inferredTemplates->get($t->name, $t) : $t,
            );
        }

        return $resultingTemplatesMap;
    }

    private function getFunctionCallResult(
        FunctionLikeDefinition $callee,
        ArgumentTypeBag $arguments,
        /* When this is a handling for method call */
        ObjectType|SelfType|null $calledOnType = null,
    ): Type {
        $returnType = $callee->getReturnType();

        if ($isSelf = $returnType instanceof SelfType && $calledOnType) {
            $returnType = $calledOnType;
        }

        $classDefinition = $calledOnType instanceof ObjectType ? $this->index->getClass($calledOnType->name) : null;

        $classContextTemplates = $calledOnType && $classDefinition
            ? (new TemplateTypesSolver)->getClassContextTemplates($calledOnType, $classDefinition)
            : [];

        $arguments = $arguments
            ->map(fn ($t, $nameOrPosition) => (new TemplateTypesSolver)->addContextTypesToTypelessParametersOfCallableArgument(
                $t,
                $nameOrPosition,
                $callee,
                $classContextTemplates,
            ));

        $templatesMap = (new TemplateTypesSolver)
            ->getFunctionContextTemplates($callee, $arguments)
            ->prepend($classContextTemplates);

        $returnType = (new TypeWalker)->map(
            $returnType,
            fn (Type $t) => $t instanceof TemplateType ? $templatesMap->get($t->name, $t) : $t,
        );

        if ($returnType instanceof Generic && ($selfOutType = $callee->getSelfOutType())) {
            $returnType->templateTypes = $this->applySelfOutType(
                $returnType->templateTypes,
                $selfOutType,
                $templatesMap,
            );
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
