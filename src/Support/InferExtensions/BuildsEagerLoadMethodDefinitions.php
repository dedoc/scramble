<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayMerge;
use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\EagerLoadRelationsList;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PropertyFetch;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\SubtractArrays;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\WithProperties;

trait BuildsEagerLoadMethodDefinitions
{
    protected function buildWithMethodDefinition(string $definingClassName, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
            $tCallback = new TemplateType('TCallback'),
        ];

        $relationsType = new Generic(ArrayMerge::class, [
            new Generic(PropertyFetch::class, [
                $tModel,
                new LiteralStringType('relations'),
            ]),
            new ConditionalType(
                $tCallback,
                new ObjectType(\Closure::class),
                new KeyedArrayType([
                    new ArrayItemType_(null, $tRelations),
                ], isList: true),
                new ConditionalType(
                    $tRelations,
                    new StringType,
                    new TemplateType('Arguments'),
                    new Generic(EagerLoadRelationsList::class, [$tRelations]),
                ),
            ),
        ]);

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'with',
                arguments: [
                    'relations' => $tRelations,
                    'callback' => $tCallback,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: $definingClassName,
            selfOutType: $this->buildSelfOutType($tModel, $relationsType),
        );
    }

    protected function buildWithoutMethodDefinition(string $definingClassName, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        $relationsType = new Generic(SubtractArrays::class, [
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
        ]);

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'without',
                arguments: [
                    'relations' => $tRelations,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: $definingClassName,
            selfOutType: $this->buildSelfOutType($tModel, $relationsType),
        );
    }

    protected function buildWithOnlyMethodDefinition(string $definingClassName, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        $relationsType = new ConditionalType(
            $tRelations,
            new StringType,
            new TemplateType('Arguments'),
            new Generic(EagerLoadRelationsList::class, [$tRelations]),
        );

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'withOnly',
                arguments: [
                    'relations' => $tRelations,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: $definingClassName,
            selfOutType: $this->buildSelfOutType($tModel, $relationsType),
        );
    }

    protected function buildSetEagerLoadsMethodDefinition(string $definingClassName, TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'setEagerLoads',
                arguments: [
                    'eagerLoad' => $tRelations,
                ],
                returnType: new SelfType(''),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: $definingClassName,
            selfOutType: $this->buildSelfOutType($tModel, $tRelations),
        );
    }

    protected function buildWithoutEagerLoadsMethodDefinition(string $definingClassName, TemplateType $tModel): ShallowFunctionDefinition
    {
        $relationsType = new KeyedArrayType([], isList: true);

        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'withoutEagerLoads',
                returnType: new SelfType(''),
            ),
            definingClassName: $definingClassName,
            selfOutType: $this->buildSelfOutType($tModel, $relationsType),
        );
    }

    private function buildSelfOutType(TemplateType $tModel, Type $relationsType): Generic
    {
        return new Generic('self', [
            new Generic(WithProperties::class, [
                $tModel,
                new KeyedArrayType([
                    new ArrayItemType_('relations', $relationsType),
                ]),
            ]),
        ]);
    }
}
