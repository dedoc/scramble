<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
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
use Dedoc\Scramble\Support\Type\SideEffects\ParentConstructCall;
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Support\Collection;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    ) {}

    public static function getInstance(): static
    {
        return app(static::class);
    }

    public function resolveFunctionReturnReferences(Scope $scope, FunctionType $functionType): void
    {
        if (static::hasResolvableReferences($returnType = $functionType->getReturnType())) {
            $resolvedReference = $this->resolve($scope, $returnType);
            $functionType->setReturnType($resolvedReference);
        }

        if ($annotatedReturnType = $functionType->getAttribute('annotatedReturnType')) {
            if (! $functionType->getAttribute('inferredReturnType')) {
                $functionType->setAttribute('inferredReturnType', clone $functionType->getReturnType());
            }

            $functionType->setReturnType(
                $this->addAnnotatedReturnType($functionType->getReturnType(), $annotatedReturnType, $scope)
            );
        }
    }

    private function addAnnotatedReturnType(Type $inferredReturnType, Type $annotatedReturnType, Scope $scope): Type
    {
        $types = $inferredReturnType instanceof Union
            ? $inferredReturnType->types
            : [$inferredReturnType];

        // @todo: Handle case when annotated return type is union.
        if ($annotatedReturnType instanceof ObjectType) {
            $annotatedReturnType->name = $this->resolveClassName($scope, $annotatedReturnType->name);
        }

        $annotatedTypeCanAcceptAnyInferredType = collect($types)
            ->some(function (Type $t) use ($annotatedReturnType) {
                if ($annotatedReturnType->accepts($t)) {
                    return true;
                }

                return $t->acceptedBy($annotatedReturnType);
            });

        if (! $annotatedTypeCanAcceptAnyInferredType) {
            return $annotatedReturnType;
        }

        return Union::wrap($types)->mergeAttributes($inferredReturnType->attributes());
    }

    public static function hasResolvableReferences(Type $type): bool
    {
        return (bool) (new TypeWalker)->first(
            $type,
            fn (Type $t) => $t instanceof AbstractReferenceType,
        );
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

        if ($resolved === $type) {
            return new UnknownType('self reference');
        }

        return $this->doResolve($resolved, $type, $scope);
    }

    private function resolveConstFetchReferenceType(Scope $scope, ConstFetchReferenceType $type)
    {
        $analyzedType = clone $type;

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

            $analyzedType->callee = $contextualCalleeName;
        }

        return (new ConstFetchTypeGetter)($scope, $analyzedType->callee, $analyzedType->constName);
    }

    private function resolveMethodCallReferenceType(Scope $scope, MethodCallReferenceType $type)
    {
        // (#self).listTableDetails()
        // (#Doctrine\DBAL\Schema\Table).listTableDetails()
        // (#TName).listTableDetails()

        $type->arguments = array_map(
            fn ($t) => $this->resolve($scope, $t),
            $type->arguments,
        );

        $calleeType = $type->callee instanceof AbstractReferenceType
            ? $this->resolve($scope, $type->callee)
            : $type->callee;

        $type->callee = $calleeType;

        if ($calleeType instanceof AbstractReferenceType) {
            throw new \LogicException('Should not happen.');
        }

        $normalizedCalleeType = $calleeType instanceof TemplateType
            ? $calleeType->is
            : $calleeType;

        $classDefinition = null;
        if ($normalizedCalleeType instanceof ObjectType) {
            $classDefinition = $this->index->getClass($normalizedCalleeType->name);
        }

        if ($normalizedCalleeType && $returnType = Context::getInstance()->extensionsBroker->getAnyMethodReturnType(new AnyMethodCallEvent(
            instance: $normalizedCalleeType,
            name: $type->methodName,
            scope: $scope,
            arguments: $type->arguments,
            methodDefiningClassName: $classDefinition
                ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index)
                : ($normalizedCalleeType instanceof ObjectType ? $normalizedCalleeType->name : null),
        ))) {
            return $returnType;
        }

        $event = null;

        // Attempting extensions broker before potentially giving up on type inference
        if (($calleeType instanceof TemplateType || $calleeType instanceof ObjectType)) {
            $unwrappedType = $calleeType instanceof TemplateType && $calleeType->is
                ? $calleeType->is
                : $calleeType;

            if ($unwrappedType instanceof ObjectType) {
                $classDefinition = $this->index->getClass($unwrappedType->name);

                $event = new MethodCallEvent(
                    instance: $unwrappedType,
                    name: $type->methodName,
                    scope: $scope,
                    arguments: $type->arguments,
                    methodDefiningClassName: $classDefinition ? $classDefinition->getMethodDefiningClassName($type->methodName, $scope->index) : $unwrappedType->name,
                );
            }

            if ($event && $returnType = Context::getInstance()->extensionsBroker->getMethodReturnType($event)) {
                return $returnType;
            }

            if ($unwrappedType instanceof ObjectType) {
                $calleeType = $unwrappedType;
            }
        }

        // (#TName).listTableDetails()
        if ($calleeType instanceof TemplateType) {
            // This maybe is not a good idea as it make references bleed into the fully analyzed
            // codebase, while at the moment of final reference resolution, we should've got either
            // a resolved type, or an unknown type.
            return new UnknownType;
        }

        if (! $classDefinition) {
            return new UnknownType;
        }

        if (! $methodDefinition = $calleeType->getMethodDefinition($type->methodName, $scope)) {
            $name = $calleeType instanceof ObjectType ? $calleeType->name : $calleeType::class;

            return new UnknownType("Cannot get a method type [$type->methodName] on type [$name]");
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

    private function resolveStaticMethodCallReferenceType(Scope $scope, StaticMethodCallReferenceType $type)
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
            $calleeType = $this->resolve($scope, $type->callee);

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

    private function resolveCallableCallReferenceType(Scope $scope, CallableCallReferenceType $type)
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
            ? $this->index->getFunctionDefinition($type->callee->name)
            : $this->resolve($scope, $type->callee);

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

    private function resolveNewCallReferenceType(Scope $scope, NewCallReferenceType $type)
    {
        $type->arguments = array_map(
            fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
            $type->arguments,
        );

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
            ->filter(fn (ClassPropertyDefinition $definition) => $definition->type instanceof TemplateType && (bool) $definition->defaultType)
            ->mapWithKeys(fn (ClassPropertyDefinition $definition) => [
                $definition->type->name => $definition->defaultType,
            ]);

        $constructorDefinition = $classDefinition->getMethodDefinition('__construct', $scope);

        $inferredConstructorParamTemplates = collect($this->resolveTypesTemplatesFromArguments(
            $classDefinition->templateTypes,
            $constructorDefinition->type->arguments ?? [],
            $this->prepareArguments($constructorDefinition, $type->arguments),
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]]);

        $inferredTemplates = $this->getParentConstructCallsTypes($classDefinition, $constructorDefinition)
            ->merge($propertyDefaultTemplateTypes)
            ->merge($inferredConstructorParamTemplates);

        $type = new Generic(
            $classDefinition->name,
            collect($classDefinition->templateTypes)
                ->map(fn (TemplateType $t) => $inferredTemplates->get($t->name, new UnknownType))
                ->toArray(),
        );

        return $this->getMethodCallsSideEffectIntroducedTypesInConstructor($type, $scope, $classDefinition, $constructorDefinition);
    }

    private function resolvePropertyFetchReferenceType(Scope $scope, PropertyFetchReferenceType $type)
    {
        $objectType = $type->object;
        if ($objectType instanceof TemplateType && $objectType->is) {
            $objectType = $objectType->is;
        }
        $objectType = $this->resolve($scope, $objectType);

        if (
            $objectType instanceof AbstractReferenceType
            || $objectType instanceof TemplateType
        ) {
            // Callee cannot be resolved.
            return $type;
        }

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

        $classDefinition = $objectType instanceof SelfType && $scope->isInClass()
            ? $scope->classDefinition()
            : $this->index->getClass($objectType->name);

        if (! $classDefinition) {
            $name = $objectType instanceof SelfType ? 'self' : $objectType->name;

            return new UnknownType("Cannot get property [$type->propertyName] type on [$name]");
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

    private function getFunctionCallResult(
        FunctionLikeDefinition $callee,
        array $arguments,
        /* When this is a handling for method call */
        ObjectType|SelfType|null $calledOnType = null,
        ?MethodCallEvent $event = null,
    ) {
        $returnType = $callee->type->getReturnType();
        $isSelf = false;

        if ($returnType instanceof SelfType && $calledOnType) {
            $isSelf = true;
            $returnType = $calledOnType;
        }

        $templateNameToIndexMap = $calledOnType instanceof Generic && ($classDefinition = $this->index->getClass($calledOnType->name))
            ? array_flip(array_map(fn ($t) => $t->name, $classDefinition->templateTypes))
            : [];
        /** @var array<string, Type> $inferredTemplates */
        $inferredTemplates = $calledOnType instanceof Generic
            ? collect($templateNameToIndexMap)->mapWithKeys(fn ($i, $name) => [$name => $calledOnType->templateTypes[$i] ?? new UnknownType])->toArray()
            : [];

        $isTemplateForResolution = function (Type $t) use ($callee, $inferredTemplates) {
            if (! $t instanceof TemplateType) {
                return false;
            }

            if (in_array($t->name, array_map(fn ($t) => $t->name, $callee->type->templates))) {
                return true;
            }

            return array_key_exists($t->name, $inferredTemplates);
        };

        if (
            ($inferredTemplates || $callee->type->templates)
            && $shouldResolveTemplatesToActualTypes = (
                (new TypeWalker)->first($returnType, $isTemplateForResolution)
                || collect($callee->sideEffects)->first(fn ($s) => $s instanceof SelfTemplateDefinition && (new TypeWalker)->first($s->type, $isTemplateForResolution))
            )
        ) {
            $inferredTemplates = array_merge($inferredTemplates, collect($this->resolveTypesTemplatesFromArguments(
                $callee->type->templates,
                $callee->type->arguments,
                $this->prepareArguments($callee, $arguments),
            ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]])->toArray());

            $returnType = (new TypeWalker)->replace($returnType->clone(), function (Type $t) use ($inferredTemplates) {
                if (! $t instanceof TemplateType) {
                    return null;
                }
                foreach ($inferredTemplates as $searchName => $replace) {
                    if ($t->name === $searchName) {
                        return $replace;
                    }
                }

                return null;
            });

            if ((new TypeWalker)->first($returnType, fn (Type $t) => in_array($t, $callee->type->templates, true))) {
                throw new \LogicException("Couldn't replace a template for function and this should never happen.");
            }
        }

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

    /**
     * Prepares the actual arguments list with which a function is going to be executed, taking into consideration
     * arguments defaults.
     *
     * @param  array  $realArguments  The list of arguments a function has been called with.
     * @return array The actual list of arguments where not passed arguments replaced with default values.
     */
    private function prepareArguments(?FunctionLikeDefinition $callee, array $realArguments)
    {
        if (! $callee) {
            return $realArguments;
        }

        return collect($callee->type->arguments)
            ->keys()
            ->map(function (string $name, int $index) use ($callee, $realArguments) {
                return $realArguments[$name] ?? $realArguments[$index] ?? $callee->argumentsDefaults[$name] ?? null;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function resolveTypesTemplatesFromArguments($templates, $templatedArguments, $realArguments)
    {
        return array_values(array_filter(array_map(function (TemplateType $template) use ($templatedArguments, $realArguments) {
            $argumentIndexName = null;
            $index = 0;
            foreach ($templatedArguments as $name => $type) {
                if ($type === $template) {
                    $argumentIndexName = [$index, $name];
                    break;
                }
                $index++;
            }
            if (! $argumentIndexName) {
                return null;
            }

            $foundCorrespondingTemplateType = $realArguments[$argumentIndexName[1]]
                ?? $realArguments[$argumentIndexName[0]]
                ?? null;

            if (! $foundCorrespondingTemplateType) {
                $foundCorrespondingTemplateType = new UnknownType;
                // throw new \LogicException("Cannot infer type of template $template->name from arguments.");
            }

            return [
                $template,
                $foundCorrespondingTemplateType,
            ];
        }, $templates)));
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

    /**
     * @return Collection<string, Type> The key is a template type name and a value is a resulting type.
     */
    private function getParentConstructCallsTypes(ClassDefinition $classDefinition, ?FunctionLikeDefinition $constructorDefinition): Collection
    {
        if (! $constructorDefinition) {
            return collect();
        }

        /** @var ParentConstructCall $firstParentConstructorCall */
        $firstParentConstructorCall = collect($constructorDefinition->sideEffects)->first(fn ($se) => $se instanceof ParentConstructCall);

        if (! $firstParentConstructorCall) {
            return collect();
        }

        if (! $classDefinition->parentFqn) {
            return collect();
        }

        $parentClassDefinition = $this->index->getClass($classDefinition->parentFqn);

        if (! $parentClassDefinition) {
            return collect();
        }

        $templateArgs = collect($this->resolveTypesTemplatesFromArguments(
            $parentClassDefinition->templateTypes,
            $parentClassDefinition->getMethodDefinition('__construct')?->type->arguments ?? [],
            $this->prepareArguments($parentClassDefinition->getMethodDefinition('__construct'), $firstParentConstructorCall->arguments),
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]]);

        return $this
            ->getParentConstructCallsTypes($parentClassDefinition, $parentClassDefinition->getMethodDefinition('__construct'))
            ->merge($templateArgs);
    }

    private function getMethodCallsSideEffectIntroducedTypesInConstructor(Generic $type, Scope $scope, ClassDefinition $classDefinition, ?FunctionLikeDefinition $constructorDefinition): Type
    {
        if (! $constructorDefinition) {
            return $type;
        }

        $mappo = new \WeakMap;
        foreach ($constructorDefinition->sideEffects as $se) {
            if (! $se instanceof MethodCallReferenceType) {
                continue;
            }

            if ((! $se->callee instanceof SelfType) && ($mappo->offsetExists($se->callee) && ! $mappo->offsetGet($se->callee) instanceof SelfType)) {
                continue;
            }

            // at this point we know that this is a method call on a self type
            $resultingType = $this->resolveMethodCallReferenceType($scope, $se);

            // $resultingType will be Self type if $this is returned, and we're in context of fluent setter

            $mappo->offsetSet($se, $resultingType);

            $methodDefinition = $se->callee instanceof ObjectType
                ? $this->index->getClass($se->callee->name)?->getMethodDefinition($se->methodName)
                : null;

            if (! $methodDefinition) {
                continue;
            }

            if (! $type instanceof ObjectType) {
                continue;
            }

            $event = new MethodCallEvent(
                instance: $type,
                name: $se->methodName,
                scope: $scope,
                arguments: $se->arguments,
                methodDefiningClassName: $type->name,
            );

            foreach ($methodDefinition->sideEffects as $sideEffect) {
                if (
                    $sideEffect instanceof SelfTemplateDefinition
                    && $type instanceof Generic
                ) {
                    $sideEffect->apply($type, $event);
                }
            }
        }

        return $type;
    }
}
