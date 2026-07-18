<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Purely for performance improvements for cases, when mapping over eloquent collection
 * creates unneeded unions which in cases of nested map calls degrade performance.
 */
class AfterEloquentCollectionDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === EloquentCollection::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $tKey = collect($definition->templateTypes)->firstOrFail('name', 'TKey');
        $tModel = collect($definition->templateTypes)->firstOrFail('name', 'TModel');

        $definition->methods['map'] = $this->buildMapMethodDefinition($tKey, $tModel);
        $definition->methods['mapWithKeys'] = $this->buildMapWithKeysMethodDefinition($tKey, $tModel);
    }

    private function buildMapMethodDefinition(TemplateType $tKey, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tMapValue = new TemplateType('TMapValue'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'map',
                arguments: [
                    'callback' => new FunctionType('{}', [$tModel, $tKey], $tMapValue),
                ],
                returnType: new Generic(StaticReference::STATIC, [$tKey, $tMapValue]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: EloquentCollection::class,
        );
    }

    private function buildMapWithKeysMethodDefinition(TemplateType $tKey, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tMapWithKeysKey = new TemplateType('TMapWithKeysKey', is: new Union([new IntegerType, new StringType])),
            $tMapWithKeysValue = new TemplateType('TMapWithKeysValue'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'mapWithKeys',
                arguments: [
                    'callback' => new FunctionType('{}', [$tModel, $tKey], new Generic('iterable', [
                        $tMapWithKeysKey,
                        $tMapWithKeysValue,
                    ])),
                ],
                returnType: new Generic(StaticReference::STATIC, [$tMapWithKeysKey, $tMapWithKeysValue]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: EloquentCollection::class,
        );
    }
}
