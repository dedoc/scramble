<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MissingType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypePath;
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
        $inferredTemplates = [];

        foreach ($templates as $template) {
            foreach (array_values($templatedArguments) as $i => $templatedParameterType) {
                $argumentType = $realArguments[$i] ?? new UnknownType;

                if ($inferredType = $this->inferTemplate($template, $templatedParameterType, $argumentType)) {
                    $inferredTemplates[$template->name] = $inferredType;

                    break;
                }
            }
        }

        return $inferredTemplates;
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

    /**
     * @param  TemplateType[]  $classTemplateTypes
     * @param  ClassPropertyDefinition[]  $properties
     * @return array<string, Type> The key is template name and the value is the inferred type.
     */
    public function inferTemplatesFromPropertyDefaults(array $classTemplateTypes, array $properties): array
    {
        $inferredTemplates = [];

        foreach ($classTemplateTypes as $template) {
            foreach ($properties as $property) {
                if (! $property->defaultType) {
                    continue;
                }

                if ($inferredType = $this->inferTemplate($template, $property->type, $property->defaultType)) {
                    $inferredTemplates[$template->name] = $inferredType;

                    break;
                }
            }

        }

        return $inferredTemplates;
    }

    private function inferTemplate(TemplateType $template, Type $typeWithTemplate, Type $type): ?Type
    {
        if (! $path = $this->findTemplatePath($template, $typeWithTemplate)) {
            return null;
        }

        return $this->getTypeByPath($type, $path);
    }

    private function findTemplatePath(TemplateType $template, Type $typeWithTemplate): ?TypePath
    {
        return TypePath::findFirst(
            $typeWithTemplate,
            fn (Type $t) => $t === $template,
        );
    }

    private function getTypeByPath(Type $type, TypePath $path): ?Type
    {
        $result = $path->getFrom($type);

        if (! $result instanceof Type) {
            // Path retrieval result should always be a type due to the found template
            return null;
        }

        return $result;
    }
}
