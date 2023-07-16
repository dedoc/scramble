<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\ClassDependency;
use Dedoc\Scramble\Support\Type\Reference\Dependency\FunctionDependency;
use Dedoc\Scramble\Support\Type\Reference\Dependency\MethodDependency;
use Dedoc\Scramble\Support\Type\Reference\Dependency\PropertyDependency;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    ) {
    }

    public static function getInstance(): static
    {
        return app(static::class);
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
        if (
            $type instanceof AbstractReferenceType
            && ! $this->checkDependencies($type)
        ) {
            //      ????      return new UnknownType();
        }

        $resultingType = RecursionGuard::run(
            $type,//->toString(),
            fn () => (new TypeWalker)->replace(
                $type,
                fn (Type $t) => $this->doResolve($t, $type, $scope),
            ),
            onInfiniteRecursion: fn () => new UnknownType('really bad self reference'),
        );

        return RecursionGuard::run(
            $resultingType,//->toString(),
            fn () => (new TypeWalker)->replace(
                $resultingType,
                fn (Type $t) => $t instanceof Union
                    ? TypeHelper::mergeTypes(...$t->types)
                    : null,
            ),
            onInfiniteRecursion: fn () => new UnknownType('really bad self reference'),
        );
    }

    private function checkDependencies(AbstractReferenceType $type)
    {
        if (! $dependencies = $type->dependencies()) {
            return true;
        }

        foreach ($dependencies as $dependency) {
            if ($dependency instanceof FunctionDependency) {
                return (bool) $this->index->getFunctionDefinition($dependency->name);
            }

            if ($dependency instanceof PropertyDependency || $dependency instanceof MethodDependency || $dependency instanceof ClassDependency) {
                if (! $classDefinition = $this->index->getClassDefinition($dependency->class)) {
                    // Maybe here the resolution can happen
                    return false;
                }

                if ($dependency instanceof PropertyDependency) {
                    return array_key_exists($dependency->name, $classDefinition->properties);
                }

                if ($dependency instanceof MethodDependency) {
                    return array_key_exists($dependency->name, $classDefinition->methods);
                }

                if ($dependency instanceof ClassDependency) {
                    return true;
                }
            }
        }

        throw new \LogicException('There are unhandled dependencies. This should not happen.');
    }

    private function doResolve(Type $t, Type $type, Scope $scope)
    {
        $resolver = function () use ($t, $scope) {
            if ($t instanceof MethodCallReferenceType) {
                return $this->resolveMethodCallReferenceType($scope, $t);
            }

            if ($t instanceof CallableCallReferenceType) {
                return $this->resolveCallableCallReferenceType($scope, $t);
            }

            if ($t instanceof NewCallReferenceType) {
                return $this->resolveNewCallReferenceType($scope, $t);
            }

            if ($t instanceof PropertyFetchReferenceType) {
                return $this->resolvePropertyFetchReferenceType($scope, $t);
            }

            return null;
        };

        if (! $resolved = $resolver()) {
            return null;
        }

        if ($resolved === $type) {
            return new UnknownType('self reference');
        }

        return $this->resolve($scope, $resolved);
    }

    private function resolveMethodCallReferenceType(Scope $scope, MethodCallReferenceType $type)
    {
        // (#self).listTableDetails()
        // (#Doctrine\DBAL\Schema\Table).listTableDetails()
        // (#TName).listTableDetails()

        $type->arguments = array_map(
            // @todo: fix resolving arguments when deep arg is reference
            fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
            $type->arguments,
        );

        $calleeType = $type->callee instanceof AbstractReferenceType
            ? $this->resolve($scope, $type->callee)
            : $type->callee;

        $type->callee = $calleeType;

        if ($calleeType instanceof AbstractReferenceType) {
            throw new \LogicException('Should not happen.');
        }

        // (#TName).listTableDetails()
        if ($calleeType instanceof TemplateType) {
            // This maybe is not a good idea as it make references bleed into the fully analyzed
            // codebase, while at the moment of final reference resolution, we should've got either
            // a resolved type, or an unknown type.
            return $type;
        }

        if (
            ($calleeType instanceof ObjectType)
            && ! array_key_exists($calleeType->name, $this->index->classesDefinitions)
            && ! $this->resolveUnknownClassResolver($calleeType->name)
        ) {
            return new UnknownType();
        }

        if (! $methodDefinition = $calleeType->getMethodDefinition($type->methodName, $scope)) {
            $name = $calleeType instanceof ObjectType ? $calleeType->name : $calleeType::class;

            return new UnknownType("Cannot get a method type [$type->methodName] on type [$name]");
        }

        return $this->getFunctionCallResult($methodDefinition, $type->arguments, $calleeType);
    }

    private function resolveUnknownClassResolver(string $className): ?ClassDefinition
    {
        try {
            $reflection = new \ReflectionClass($className);

            if (Str::contains($reflection->getFileName(), '/vendor/')) {
                return null;
            }

            return (new ClassAnalyzer($this->index))->analyze($className);
        } catch (\ReflectionException) {
        }

        return null;
    }

    private function resolveCallableCallReferenceType(Scope $scope, CallableCallReferenceType $type)
    {
        $calleeType = $type->callee instanceof CallableStringType
            ? $this->index->getFunctionDefinition($type->callee->name)
            : $this->resolve($scope, $type->callee);

        if (! $calleeType) {
            // Callee cannot be resolved from index.
            return new UnknownType();
        }

        if ($calleeType instanceof FunctionType) { // When resolving into a closure.
            $calleeType = new FunctionLikeDefinition($calleeType);
        }

        // @todo: callee now can be either in index or not, add support for other cases.
        if (! $calleeType instanceof FunctionLikeDefinition) {
            // Callee cannot be resolved.
            return new UnknownType();
        }

        return $this->getFunctionCallResult($calleeType, $type->arguments);
    }

    private function resolveNewCallReferenceType(Scope $scope, NewCallReferenceType $type)
    {
        $type->arguments = array_map(
            fn ($t) => $t instanceof AbstractReferenceType ? $this->resolve($scope, $t) : $t,
            $type->arguments,
        );

        if (
            ! array_key_exists($type->name, $this->index->classesDefinitions)
            && ! $this->resolveUnknownClassResolver($type->name)
        ) {
            return new UnknownType();
        }

        $classDefinition = $this->index->getClassDefinition($type->name);

        if (! $classDefinition->templateTypes) {
            return new ObjectType($type->name);
        }

        $inferredTemplates = collect($this->resolveTypesTemplatesFromArguments(
            $classDefinition->templateTypes,
            $classDefinition->getMethodDefinition('__construct', $scope)->type->arguments ?? [],
            $this->prepareArguments($classDefinition->getMethodDefinition('__construct', $scope), $type->arguments),
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]]);

        return new Generic(
            $classDefinition->name,
            collect($classDefinition->templateTypes)
                ->map(fn (TemplateType $t) => $inferredTemplates->get($t->name, new UnknownType()))
                ->toArray(),
        );
    }

    private function resolvePropertyFetchReferenceType(Scope $scope, PropertyFetchReferenceType $type)
    {
        if (
            ($type->object instanceof ObjectType)
            && ! array_key_exists($type->object->name, $this->index->classesDefinitions)
            && ! $this->resolveUnknownClassResolver($type->object->name)
        ) {
            // Class is not indexed, and we simply cannot get an info from it.
            return $type;
        }

        $objectType = $this->resolve($scope, $type->object);

        if (
            $objectType instanceof AbstractReferenceType
            || $objectType instanceof TemplateType
        ) {
            // Callee cannot be resolved.
            return $type;
        }

        if (! $objectType instanceof ObjectType && ! $objectType instanceof SelfType) {
            return new UnknownType();
        }

        $classDefinition = $objectType instanceof SelfType && $scope->isInClass()
            ? $scope->classDefinition()
            : ($objectType instanceof ObjectType
                ? $this->index->getClassDefinition($objectType->name)
                : null);

        if (! $classDefinition) {
            $name = $objectType instanceof SelfType ? 'self' : $objectType->name;

            return new UnknownType("Cannot get property [$type->propertyName] type on [$name]");
        }

        return $objectType->getPropertyType($type->propertyName);
    }

    private function getFunctionCallResult(
        FunctionLikeDefinition $callee,
        array $arguments,
        /* When this is a handling for method call */
        ObjectType|SelfType $calledOnType = null,
    ) {
        $returnType = $callee->type->getReturnType();
        $isSelf = false;

        if ($returnType instanceof SelfType && $calledOnType) {
            $isSelf = true;
            $returnType = $calledOnType;
        }

        $templateNameToIndexMap = $calledOnType instanceof Generic && ($classDefinition = $this->index->getClassDefinition($calledOnType->name))
            ? array_flip(array_map(fn ($t) => $t->name, $classDefinition->templateTypes))
            : [];
        /** @var array<string, Type> $inferredTemplates */
        $inferredTemplates = $calledOnType instanceof Generic
            ? collect($templateNameToIndexMap)->mapWithKeys(fn ($i, $name) => [$name => $calledOnType->templateTypes[$i] ?? new UnknownType()])->toArray()
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

            $returnType = (new TypeWalker)->replace($returnType, function (Type $t) use ($inferredTemplates) {
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
            ) {
                $templateType = $sideEffect->type instanceof TemplateType
                    ? collect($inferredTemplates)->get($sideEffect->type->name, new UnknownType())
                    : $sideEffect->type;

                if (! isset($templateNameToIndexMap[$sideEffect->definedTemplate])) {
                    throw new \LogicException('Should not happen');
                }

                $templateIndex = $templateNameToIndexMap[$sideEffect->definedTemplate];

                $returnType->templateTypes[$templateIndex] = $templateType;
            }
        }

        return $returnType;
    }

    /**
     * Prepares the actual arguments list with which a function is going to be executed, taking into consideration
     * arguments defaults.
     *
     * @param  ?FunctionLikeDefinition  $callee
     * @param  array  $realArguments The list of arguments a function has been called with.
     * @return array The actual list of arguments where not passed arguments replaced with default values.
     */
    private function prepareArguments(?FunctionLikeDefinition $callee, array $realArguments)
    {
        if (! $callee) {
            return $realArguments;
        }

        /*
         * @todo $realArguments for now is considered only by index, not by names.
         */
        return collect($callee->type->arguments)
            ->keys()
            ->map(function (string $name, int $index) use ($callee, $realArguments) {
                return $realArguments[$index] ?? $callee->argumentsDefaults[$name] ?? null;
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
                $foundCorrespondingTemplateType = new UnknownType();
                // throw new \LogicException("Cannot infer type of template $template->name from arguments.");
            }

            return [
                $template,
                $foundCorrespondingTemplateType,
            ];
        }, $templates)));
    }
}
