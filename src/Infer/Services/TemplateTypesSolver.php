<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class TemplateTypesSolver
{
    public function getClassContextTemplates(ObjectType $type, ClassDefinition $classDefinition)
    {
        if (! $type instanceof Generic) {
            return [];
        }

        return collect($classDefinition->templateTypes)->mapWithKeys(fn ($t, $index) => [
            $t->name => $type->templateTypes[$index] ?? new UnknownType,
        ])->toArray();
    }

    public function getFunctionContextTemplates(FunctionLikeDefinition $functionLikeDefinition, array|ArgumentsBag $arguments)
    {
        return collect($this->resolveTypesTemplatesFromArguments(
            $functionLikeDefinition->type->templates,
            $functionLikeDefinition->type->arguments,
            $this->prepareArguments($functionLikeDefinition, $arguments),
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]])->toArray();
    }

    public function getClassConstructorContextTemplates(ClassDefinition $classDefinition, ?FunctionLikeDefinition $functionLikeDefinition, array|ArgumentsBag $arguments)
    {
        return collect($this->resolveTypesTemplatesFromArguments(
            ($functionLikeDefinition->type->templates ?? []) + $classDefinition->templateTypes,
            ($functionLikeDefinition->type->arguments ?? []),
            $this->prepareArguments($functionLikeDefinition, $arguments),
        ))->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]])->toArray();
    }

    /**
     * Prepares the actual arguments list with which a function is going to be executed, taking into consideration
     * arguments defaults.
     *
     * @param  array<array-key, Type>  $realArguments  The list of arguments a function has been called with.
     * @return array<int, Type> The actual list of arguments where not passed arguments replaced with default values.
     */
    private function prepareArguments(?FunctionLikeDefinition $callee, array|ArgumentsBag $realArguments): array
    {
        if (! $callee) {
            return is_array($realArguments) ? $realArguments : $realArguments->all();
        }

        $argumentsBag = is_array($realArguments) ? new ArrayArgumentsBag($realArguments) : $realArguments;

        return collect($callee->type->arguments)
            ->keys()
            ->map(function (string $name, int $index) use ($callee, $argumentsBag) {
                return $argumentsBag->getArgument(new ArgumentPosition($index, $name), $callee->argumentsDefaults[$name] ?? null);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  TemplateType[]  $templates
     * @param  array<string, Type>  $templatedArguments
     * @param  array<int, Type>  $realArguments
     * @return array{0: TemplateType, 1: Type}[]
     */
    private function resolveTypesTemplatesFromArguments($templates, $templatedArguments, $realArguments): array
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

    /**
     * For a given generic type, defined a template type by the template type name.
     */
    public function defineTemplateTypes(?ClassDefinition $classDefinition, Generic $type, string $definedTemplate, Type $definedType)
    {
        $templateNameToIndexMap = array_flip(array_map(fn ($t) => $t->name, $classDefinition->templateTypes ?? []));

        if (! isset($templateNameToIndexMap[$definedTemplate])) {
            throw new \LogicException('Should not happen');
        }

        $templateIndex = $templateNameToIndexMap[$definedTemplate];

        $type->templateTypes[$templateIndex] = $definedType;
    }
}
