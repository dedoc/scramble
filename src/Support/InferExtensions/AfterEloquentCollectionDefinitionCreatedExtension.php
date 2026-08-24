<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayMerge;
use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\EagerLoadRelationsList;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\PropertyFetch;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\WithProperties;
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
        $definition->methods['load'] = $this->buildLoadMethodDefinition($tModel);
        $definition->methods['loadMissing'] = $this->buildLoadMissingMethodDefinition($tModel);
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

    private function buildLoadMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'load',
                arguments: [
                    'relations' => $tRelations,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: EloquentCollection::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType,
                $this->buildLoadRelationsReturnType($tModel, $tRelations),
            ]),
        );
    }

    private function buildLoadMissingMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'loadMissing',
                arguments: [
                    'relations' => $tRelations,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: EloquentCollection::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType,
                $this->buildLoadRelationsReturnType($tModel, $tRelations),
            ]),
        );
    }

    private function buildLoadRelationsReturnType(TemplateType $tModel, TemplateType $tRelations): Generic
    {
        /**
         * @see Collection::load
         * @see Collection::loadMissing
         *
         * @return WithProperties<
         *     TModel,
         *     array{
         *         relations: ArrayMerge<
         *             PropertyFetch<TModel, 'relations'>,
         *             TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
         *         >
         *     }
         * >
         */
        return new Generic(WithProperties::class, [
            $tModel,
            new KeyedArrayType([
                new ArrayItemType_('relations', new Generic(ArrayMerge::class, [
                    new Generic(PropertyFetch::class, [
                        $tModel,
                        new LiteralStringType('relations'),
                    ]),
                    new ConditionalType(
                        $tRelations,
                        new StringType,
                        new TemplateType('Arguments'),
                        new Generic(EagerLoadRelationsList::class, [$tRelations]),
                    ),
                ])),
            ]),
        ]);
    }
}
