<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\AssignmentInfo\AssignmentInfo;
use Dedoc\Scramble\Support\Type\AssignmentInfo\SelfPropertyAssignmentInfo;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
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
            && ! array_key_exists($type->callee->name, $this->index->classes)
            && ! $unknownClassHandler($type->callee->name)
        ) {
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

        if (
            $calleeType instanceof ObjectType
            && array_key_exists($calleeType->name, $this->index->classes)
        ) {
            $calleeType = $this->index->getClassType($calleeType->name);
        }

        if (! array_key_exists($type->methodName, $calleeType->methods)) {
            return new UnknownType("Cannot get type of calling method [$type->methodName] on object [$calleeType->name]");
        }

        return $this->getFunctionCallResult($calleeType->methods[$type->methodName], $type->arguments, $calleeType);
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

    private function getFunctionCallResult(
        FunctionType $calleeType,
        array $arguments,
        /* When this is a handling for method call */
        ?ObjectType $calledOnType = null,
    )
    {
        $returnType = $calleeType->getReturnType();

        $inferredTemplates = [];

        if (
            $calleeType->templates
            && $shouldResolveTemplatesToActualTypes = (
                (new TypeWalker)->firstPublic($returnType, fn (Type $t) => in_array($t, $calleeType->templates))
                || collect($calleeType->propertyAssignments)->first(fn (AssignmentInfo $i) => (new TypeWalker)->firstPublic($i->getAssignedType(), fn (Type $t) => in_array($t, $calleeType->templates)))
            )
        ) {
            $inferredTemplates = $this->resolveTypesTemplatesFromArguments(
                $calleeType->templates,
                $calleeType->arguments,
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

            if ((new TypeWalker)->firstPublic($returnType, fn (Type $t) => in_array($t, $calleeType->templates))) {
                throw new \LogicException("Couldn't replace a template for function and this should never happen.");
            }
        }

        foreach ($calleeType->propertyAssignments as $assignment) {
            if (
                $assignment instanceof SelfPropertyAssignmentInfo
                && $calledOnType
                && $returnType instanceof ObjectType
                && $returnType->name === $calledOnType->name
                // && array_key_exists($assignment->property, $returnType->properties)
            ) {
                $returnType->properties[$assignment->property] = (new TypeWalker)->replacePublic($assignment->getAssignedType(), function (Type $t) use ($inferredTemplates) {
                    foreach ($inferredTemplates as [$search, $replace]) {
                        if ($t === $search) {
                            return $replace;
                        }
                    }
                    return null;
                });
            }
        }

        return $returnType;
    }

    private function resolveTypesTemplatesFromArguments($templates, $templatedArguments, $realArguments)
    {
        return array_map(function (TemplateType $template) use ($templatedArguments, $realArguments) {
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
                throw new \LogicException("Cannot infer type of template $template->name from arguments.");
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
        }, $templates);
    }
}
