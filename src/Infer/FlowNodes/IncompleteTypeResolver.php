<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use WeakMap;

class IncompleteTypeResolver
{
    private WeakMap $visitedNodes;

    public function __construct(private readonly Index $index)
    {
        $this->visitedNodes = new WeakMap;
    }

    public function resolve(Type $type)
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

    protected function doResolve(Type $type): Type
    {
        if ($this->visitedNodes->offsetExists($type)) {
            return new UnknownType('Recursive resolution');
        }
        $this->visitedNodes->offsetSet($type, true);

        if ($type instanceof FunctionType) {
            return $this->resolveFunctionType($type);
        }

        if (! $type instanceof AbstractReferenceType) {
            return $type;
        }

        if ($type instanceof CallableCallReferenceType) {
            $callee = $type->callee instanceof CallableStringType
                ? $this->index->getFunction($type->callee->name)
                : $type->callee;

            $functionType = $callee
                ? $this->resolve($callee)
                : new UnknownType;

            if ($functionType instanceof TemplateType) {
                $functionType = $functionType->is ?: new MixedType;
            }

            if ($functionType instanceof FunctionType) {
                return $this->resolveFunctionLikeCall(
                    $functionType,
                    $type->arguments,
                );
            }
        }

        if ($type instanceof NewCallReferenceType) {
            $classDefinition = $this->index->getClass($type->name);

            if (! $classDefinition?->templateTypes) {
                return new ObjectType($type->name);
            }

            return new Generic(
                $type->name,
                array_values($this->inferClassTemplateTypesFromNewCall($classDefinition, $type->arguments)),
            );
        }
//
//        if ($type instanceof MethodCallReferenceType) {
//            $callee = $this->resolve($type->callee);
//
//            if ($callee instanceof TemplateType) {
//                $callee = $callee->is ?: new MixedType;
//            }
//
//            if ($callee instanceof ObjectType) {
//                $classDefinition = $this->index->getClass($callee->name);
//
//                $functionType = $classDefinition?->getMethod($type->methodName);
//
//                if ($functionType) {
//                    $functionType = $this->resolve($functionType);
//                }
//
//                if ($functionType instanceof FunctionType) {
//                    return $this->resolveFunctionLikeCall(
//                        $functionType,
//                        $type->arguments,
//                        $this->inferTemplateTypesFromObject($classDefinition, $callee),
//                    );
//                }
//            }
//        }

        return new UnknownType('Cannot resolve reference type');
    }

    /**
     * @param FunctionType $functionType *Resolved* function type
     */
    private function resolveFunctionLikeCall(FunctionType $functionType, array|callable $arguments, array $inferredTemplateTypes = [])
    {
        $returnType = $functionType->returnType;

        $returnedTemplateTypes = $functionType->getAttribute('returnedTemplateTypes') ?: [];
        $functionTemplatesInReturn = collect($functionType->templates)
            ->filter(fn ($t) => in_array($t, $returnedTemplateTypes))
            ->all();

        // if no templates in return - no need to resolve individual arguments!
        if (! $functionTemplatesInReturn && ! $inferredTemplateTypes) {
            return $returnType;
        }

        $inferredTemplateTypes = array_merge(
            $inferredTemplateTypes,
            $this->inferTemplateTypesFromCall($functionType, $arguments, $functionTemplatesInReturn),
        );

        return (new TypeWalker)->map(
            $returnType,
            fn (Type $t) => $t instanceof TemplateType
                ? $inferredTemplateTypes[$t->name] ?? $t
                : $t,
        );
    }

    /**
     * Used to prepare the list of concrete template types when an object is constructed using new
     * keyword. For example, given the call (new Foo)(int(34)), this function will return
     * [
     *   'TA' => int(34)
     * ]
     * assuming that TA is a template type of a class and is accepted in constructor.
     *
     * @param ClassDefinition $classDefinition
     * @param array|callable $arguments
     * @return array
     */
    private function inferClassTemplateTypesFromNewCall(ClassDefinition $classDefinition, array|callable $arguments): array
    {
        $classDefinition->ensureFullyAnalyzed($this->index);

        // traverse through template default types and resolve them all

        $constructorDefinition = $classDefinition->getMethodDefinitionWithoutAnalysis('__construct');
        $constructorType = $constructorDefinition
            ? $this->resolve($constructorDefinition->type)
            : new FunctionType('__construct', returnType: new VoidType);

        $newCallInferredTemplates = $this->inferTemplateTypesFromCall($constructorType, $arguments, [
            ...$classDefinition->templateTypes,
            ...$constructorType->templates,
        ]);

        $classInferredTemplates = [];
        // merge default template types, keep in mind that they in turn can also be the template types
        foreach ($classDefinition->templateTypes as $templateType) {
            if (array_key_exists($templateType->name, $newCallInferredTemplates)) {
                $classInferredTemplates[$templateType->name] = $newCallInferredTemplates[$templateType->name];
                continue;
            }

            $classInferredTemplates[$templateType->name] = (new TypeWalker)->map(
                $templateType->default ?: new MixedType,
                fn (Type $t) => $t instanceof TemplateType
                    ? $newCallInferredTemplates[$t->name] ?? $t
                    : $t,
            );
        }

        return $classInferredTemplates;
    }

    private function inferTemplateTypesFromCall(FunctionType $functionType, array|callable $arguments, array $templatesToInfer): array
    {
        $arguments = is_callable($arguments) ? $arguments() : $arguments;

        $parametersOfTemplates = collect($functionType->arguments) // these are PARAMETERS!!!
            ->map(fn ($type, $name) => [$name, $type])
            ->values()
            ->map(fn ($nameTypeTuple, $i) => [$i, ...$nameTypeTuple])
            ->filter(fn ($indexNameTypeTuple, $i) => (new TypeWalker)->first($indexNameTypeTuple[2], fn ($t) => in_array($t, $templatesToInfer)))
            ->all();

        // first we map parameters to arguments
        $parameterArgumentPairs = [];
        foreach ($parametersOfTemplates as $parametersOfTemplate) {
            [$index, $name, $parameterType] = $parametersOfTemplate;
            $argumentType = $arguments[$index] ?? $arguments[$name] ?? new MixedType; // @todo just use the argument default here!!!
            $parameterArgumentPairs[] = [$parameterType, $argumentType];
        }

        /*
         * Now as we have parameters mapped, we can start resolve templates. Templates are inferred in the
         * order of parameters declaration.
         */
        $result = [];
        foreach ($parameterArgumentPairs as [$parameterType, $argumentType]) {
            foreach ($templatesToInfer as $templateType) {
                $parameterTypePath = (new TypeWalker)->findPathToFirst($parameterType, fn ($t) => $t === $templateType);
                if ($parameterTypePath === null) {
                    continue;
                }
                try {
                    $templateInferredType = (new TypeWalker)->getTypeByPath($argumentType, $parameterTypePath);
                } catch (\Throwable $e) {
                    // @todo proper exception
                    $templateInferredType = new UnknownType('Cannot get a type by path');
                }
                $result[$templateType->name] = $templateInferredType;
            }
        }

        return $result;
    }

    private function resolveFunctionType(FunctionType $type): FunctionType
    {
        if ($resolvedType = $type->getAttribute('resolvedType')) {
            return $resolvedType;
        }

        $resolvedReturnType = $this->resolve($type->returnType);

        $templateTypes = (new TypeWalker)->findAll(
            $resolvedReturnType,
            fn (Type $t) => $t instanceof TemplateType,
        );

        $functionType = clone $type;
        $functionType->templates = array_values(array_filter(
            $functionType->templates,
            fn ($t) => in_array($t, $templateTypes),
        ));
        $functionType->arguments = array_map(
            fn ($t) => $t instanceof TemplateType
                ? in_array($t, $templateTypes) ? $t : ($t->is ?: new MixedType)
                : $t,
            $functionType->arguments
        );
        $functionType->returnType = $resolvedReturnType;
        $functionType->setAttribute('returnedTemplateTypes', $templateTypes);

        $type->setAttribute('resolvedType', $functionType);

        return $functionType;
    }
}
