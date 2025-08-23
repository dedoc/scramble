<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MissingType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class TemplateTypesSolver
{
    /** @return array<string, Type> */
    public function getClassContextTemplates(ObjectType $type, ClassDefinition $classDefinition): array
    {
        if (! $type instanceof Generic) {
            return [];
        }

        return collect($classDefinition->templateTypes)->mapWithKeys(fn ($t, $index) => [
            $t->name => $type->templateTypes[$index] ?? new UnknownType,
        ])->all();
    }

    public function getFunctionContextTemplates(FunctionLikeDefinition $functionLikeDefinition, ArgumentTypeBag $arguments): TemplatesMap
    {
        return new TemplatesMap(
            bag: $this->resolveTypesTemplatesFromArguments(
                $functionLikeDefinition->type->templates,
                $functionLikeDefinition->type->arguments,
                $this->prepareArguments($functionLikeDefinition, $arguments),
            ),
            arguments: $arguments,
        );
    }

    public function getClassConstructorContextTemplates(ClassDefinition $classDefinition, ?FunctionLikeDefinition $functionLikeDefinition, ArgumentTypeBag $arguments): TemplatesMap
    {
        return new TemplatesMap(
            bag: $this->resolveTypesTemplatesFromArguments(
                ($functionLikeDefinition->type->templates ?? []) + $classDefinition->templateTypes,
                ($functionLikeDefinition->type->arguments ?? []),
                $this->prepareArguments($functionLikeDefinition, $arguments),
            ),
            arguments: $arguments,
        );
    }

    /**
     * Prepares the actual arguments list with which a function is going to be executed, taking into consideration
     * arguments defaults.
     *
     * @return array<int, Type> The actual list of arguments where not passed arguments replaced with default values.
     */
    private function prepareArguments(?FunctionLikeDefinition $callee, ArgumentTypeBag $argumentTypeBag): array
    {
        if (! $callee) {
            return $argumentTypeBag->all();
        }

        return collect($callee->type->arguments)
            ->keys()
            ->map(function (string $name, int $index) use ($callee, $argumentTypeBag) {
                return $argumentTypeBag->get($name, $index, default: $callee->argumentsDefaults[$name] ?? null);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  TemplateType[]  $templates
     * @param  array<string, Type>  $templatedArguments
     * @param  array<int, Type>  $realArguments
     * @return array<string, Type>
     */
    private function resolveTypesTemplatesFromArguments(array $templates, array $templatedArguments, array $realArguments): array
    {
        return collect($templates)
            ->map(function (TemplateType $template) use ($templatedArguments, $realArguments) {
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
                }

                return [
                    $template,
                    $foundCorrespondingTemplateType,
                ];
            })
            ->filter()
            ->values()
            ->mapWithKeys(fn ($searchReplace) => [$searchReplace[0]->name => $searchReplace[1]])
            ->all();
    }

    /**
     * @param  TemplateType[]  $templateTypes
     * @return Type[]
     */
    public function getGenericCreationTemplatesWithDefaults(array $templateTypes, TemplatesMap $templatesMap): array
    {
        $mappedTypes = collect($templateTypes)
            ->map(function (TemplateType $t) use ($templatesMap) {
                $type = $templatesMap->get($t->name, new MissingType);

                if ($type instanceof MissingType) {
                    return $t->default ? $type : new UnknownType;
                }

                return $type;
            })
            ->all();

        $nonMissingTypeSeen = false;
        foreach (array_reverse($mappedTypes, preserve_keys: true) as $key => $type) {
            if (! $type instanceof MissingType) {
                $nonMissingTypeSeen = true;
            }

            if ($nonMissingTypeSeen && $type instanceof MissingType) {
                $mappedTypes[$key] = ($templateTypes[$key]->default ?? new UnknownType('Should have template default here but doesnt have for some reason'));

                continue;
            }

            if ($type instanceof MissingType) {
                unset($mappedTypes[$key]);
            }
        }

        return $mappedTypes;
    }
}
