<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\AssignmentInfo\AssignmentInfo;
use Dedoc\Scramble\Support\Type\AssignmentInfo\SelfPropertyAssignmentInfo;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use function Pest\Laravel\instance;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    ) {
    }

    public static function hasResolvableReferences(Type $type): bool
    {
        return (bool) (new TypeWalker)->firstPublic(
            $type,
            fn (Type $t) => $t instanceof AbstractReferenceType,
        );
    }

    public function resolve(Type $type, callable $unknownClassHandler = null): Type
    {
        $unknownClassHandler = $unknownClassHandler ?: fn () => null;

        return (new TypeWalker)->replacePublic(
            $type,
            function (Type $t) use ($type, $unknownClassHandler) {
                $resolver = function () use ($t, $unknownClassHandler) {
                    if ($t instanceof MethodCallReferenceType) {
                        return $this->resolveMethodCallReferenceType($t, $unknownClassHandler);
                    }

                    if ($t instanceof CallableCallReferenceType) {
                        return $this->resolveCallableCallReferenceType($t);
                    }

                    if ($t instanceof NewCallReferenceType) {
                        return $this->resolveNewCallReferenceType($t, $unknownClassHandler);
                    }

                    return null;
                };

                if (! $resolved = $resolver()) {
                    return null;
                }

                if ($resolved === $type) {
                    return $type;
                    return new UnknownType('self reference');
                }

//                if ($resolved instanceof AbstractReferenceType) {
//                    return $resolveNested ? $resolved : new UnknownType();
//                }

                return $this->resolve($resolved, $unknownClassHandler);
            },
        );
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type, callable $unknownClassHandler)
    {
        if (
            ($type->callee instanceof ObjectType)
            && ! array_key_exists($type->callee->name, $this->index->classesDefinitions)
            && ! $unknownClassHandler($type->callee->name)
        ) {
            // Class is not indexed, and we simply cannot get an info from it.
            return $type;
        }

        $calleeType = $this->resolve($type->callee, $unknownClassHandler);

        if (
            $calleeType instanceof AbstractReferenceType
            || $calleeType instanceof TemplateType
        ) {
            // Callee cannot be resolved.
            return $type;
        }

        if ($calleeType instanceof UnknownType) {
            // This unknown is legit. On line 97 should be processed correctly.
            return new UnknownType();
        }

        if (! $calleeType instanceof ObjectType) {
            return new UnknownType();
        }

        $calleeDefinition = $this->index->getClassDefinition($calleeType->name);

        if (! array_key_exists($type->methodName, $calleeDefinition->methods)) {
            return new UnknownType("Cannot get type of calling method [$type->methodName] on object [$calleeType->name]");
        }

        return $this->getFunctionCallResult($calleeDefinition->methods[$type->methodName], $type->arguments, $calleeType);
    }

    private function resolveCallableCallReferenceType(CallableCallReferenceType $type)
    {
        $calleeType = $this->index->getFunctionType($type->callee);

        if (! $calleeType) {
            // Callee cannot be resolved from index.
            return $type;
        }

        // @todo: callee now can be either in index or not, add support for other cases.
        // if ($calleeType instanceof AbstractReferenceType) {
        //    // Callee cannot be resolved.
        //    return $type;
        //}

        return $this->getFunctionCallResult($calleeType, $type->arguments);
    }

    private function resolveNewCallReferenceType(NewCallReferenceType $type, callable $unknownClassHandler)
    {
        if (
            ! array_key_exists($type->name, $this->index->classesDefinitions)
            && ! $unknownClassHandler($type->name)
        ) {
            // Class is not indexed, and we simply cannot get an info from it.
            return $type;
        }

        $classDefinition = $this->index->getClassDefinition($type->name);

        if (! $classDefinition->templateTypes) {
            return new ObjectType($type->name);
        }

        $inferredTemplates = collect($this->resolveTypesTemplatesFromArguments(
            $classDefinition->templateTypes,
            $classDefinition->methods['__construct']->type->arguments ?? [],
            $type->arguments,
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]]);

        return new Generic(
            $classDefinition->name,
            collect($classDefinition->templateTypes)->mapWithKeys(fn (TemplateType $t) => [
                $t->name => $inferredTemplates->get($t->name, new UnknownType()),
            ])->toArray(),
        );
    }

    private function getFunctionCallResult(
        FunctionLikeDefinition $callee,
        array $arguments,
        /* When this is a handling for method call */
        ?ObjectType $calledOnType = null,
    )
    {
        $returnType = $callee->type->getReturnType();
        $isSelf = false;

        if ($returnType instanceof SelfType && $calledOnType) {
            $isSelf = true;
            $returnType = $calledOnType;
        }

        $inferredTemplates = [];

        if (
            $callee->type->templates
            && $shouldResolveTemplatesToActualTypes = (
                (new TypeWalker)->firstPublic($returnType, fn (Type $t) => in_array($t, $callee->type->templates))
                || collect($callee->sideEffects)->first(fn ($s) => $s instanceof SelfTemplateDefinition && (new TypeWalker)->firstPublic($s->type, fn (Type $t) => in_array($t, $callee->type->templates)))
            )
        ) {
            $inferredTemplates = $this->resolveTypesTemplatesFromArguments(
                $callee->type->templates,
                $callee->type->arguments,
                $arguments,
            );

            $returnType = (new TypeWalker)->replacePublic($returnType, function (Type $t) use ($inferredTemplates) {
                foreach ($inferredTemplates as [$search, $replace]) {
                    if ($t === $search) {
                        return $replace;
                    }
                }
                return null;
            });

            if ((new TypeWalker)->firstPublic($returnType, fn (Type $t) => in_array($t, $callee->type->templates))) {
                throw new \LogicException("Couldn't replace a template for function and this should never happen.");
            }
        }

        $inferredTemplatesByTemplateName = collect($inferredTemplates)
            ->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]]);

        foreach ($callee->sideEffects as $sideEffect) {
            if (
                $sideEffect instanceof SelfTemplateDefinition
                && $isSelf
                && $returnType instanceof Generic
            ) {
                $templateType = $sideEffect->type instanceof TemplateType
                    ? $inferredTemplatesByTemplateName->get($sideEffect->type->name, new UnknownType())
                    : $sideEffect->type;

                $returnType->templateTypesMap[$sideEffect->definedTemplate] = $templateType;
            }
        }

        return $returnType;
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
                throw new \LogicException("Cannot infer type of template $template->name from arguments.");
            }

            return [
                $template,
                $foundCorrespondingTemplateType,
            ];
        }, $templates)));
    }
}
