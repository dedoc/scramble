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
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PropertyFetch;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\SubtractArrays;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\WithProperties;
use Illuminate\Database\Eloquent\Builder;

class AfterEloquentBuilderDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Builder::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $tModel = collect($event->classDefinition->templateTypes)->firstWhere('name', 'TModel');
        if (! $tModel) {
            $event->classDefinition->templateTypes = array_merge(
                $event->classDefinition->templateTypes,
                [$tModel = new TemplateType('TModel')],
            );
        }

        $event->classDefinition->methods['with'] = $this->buildWithMethodDefinition($tModel);
        $event->classDefinition->methods['without'] = $this->buildWithoutMethodDefinition($tModel);
        $event->classDefinition->methods['withOnly'] = $this->buildWithOnlyMethodDefinition($tModel);
        $event->classDefinition->methods['setEagerLoads'] = $this->buildSetEagerLoadsMethodDefinition($tModel);
        $event->classDefinition->methods['withoutEagerLoads'] = $this->buildWithoutEagerLoadsMethodDefinition($tModel);
    }

    private function buildWithMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
            $tCallback = new TemplateType('TCallback'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'with',
                arguments: [
                    'relations' => $tRelations,
                    'callback' => $tCallback,
                ],
                /**
                 * @see Builder::with
                 *
                 * @return Builder<
                 *     WithProperties<
                 *         TModel,
                 *         array{
                 *             relations: ArrayMerge<
                 *                 PropertyFetch<TModel, 'relations'>,
                 *                 TCallback is Closure
                 *                     ? list{TRelations}
                 *                     : (TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>)
                 *             >
                 *         }
                 *     >
                 * >
                 */
                returnType: new Generic(Builder::class, [
                    new Generic(WithProperties::class, [
                        $tModel,
                        new KeyedArrayType([
                            new ArrayItemType_('relations', new Generic(ArrayMerge::class, [
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
                            ])),
                        ]),
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithoutMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'without',
                arguments: [
                    'relations' => $tRelations,
                ],
                /**
                 * @see Builder::without
                 *
                 * @return Builder<
                 *     WithProperties<
                 *         TModel,
                 *         array{
                 *             relations: SubtractArrays<
                 *                 PropertyFetch<TModel, 'relations'>,
                 *                 TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
                 *             >
                 *         }
                 *     >
                 * >
                 */
                returnType: new Generic(Builder::class, [
                    new Generic(WithProperties::class, [
                        $tModel,
                        new KeyedArrayType([
                            new ArrayItemType_('relations', new Generic(SubtractArrays::class, [
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
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithOnlyMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        $templates = [
            $tRelations = new TemplateType('TRelations'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'withOnly',
                arguments: [
                    'relations' => $tRelations,
                ],
                /**
                 * @see Builder::withOnly
                 *
                 * @return Builder<
                 *     WithProperties<
                 *         TModel,
                 *         array{
                 *             relations: TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
                 *         }
                 *     >
                 * >
                 */
                returnType: new Generic(Builder::class, [
                    new Generic(WithProperties::class, [
                        $tModel,
                        new KeyedArrayType([
                            new ArrayItemType_('relations', new ConditionalType(
                                $tRelations,
                                new StringType,
                                new TemplateType('Arguments'),
                                new Generic(EagerLoadRelationsList::class, [$tRelations]),
                            )),
                        ]),
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildSetEagerLoadsMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
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
                /**
                 * @see Builder::setEagerLoads
                 *
                 * @return Builder<
                 *     WithProperties<
                 *         TModel,
                 *         array{relations: TRelations}
                 *     >
                 * >
                 */
                returnType: new Generic(Builder::class, [
                    new Generic(WithProperties::class, [
                        $tModel,
                        new KeyedArrayType([
                            new ArrayItemType_('relations', $tRelations),
                        ]),
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithoutEagerLoadsMethodDefinition(TemplateType $tModel): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'withoutEagerLoads',
                /**
                 * @see Builder::withoutEagerLoads
                 *
                 * @return Builder<
                 *     WithProperties<
                 *         TModel,
                 *         array{relations: list{}}
                 *     >
                 * >
                 */
                returnType: new Generic(Builder::class, [
                    new Generic(WithProperties::class, [
                        $tModel,
                        new KeyedArrayType([
                            new ArrayItemType_('relations', new KeyedArrayType([], isList: true)),
                        ]),
                    ]),
                ]),
            ),
            definingClassName: Builder::class,
        );
    }
}
