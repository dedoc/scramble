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
use Dedoc\Scramble\Support\Type\SelfType;
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
        $event->classDefinition->methods['with'] = $this->buildWithMethodDefinition();
        $event->classDefinition->methods['without'] = $this->buildWithoutMethodDefinition();
        $event->classDefinition->methods['withOnly'] = $this->buildWithOnlyMethodDefinition();
        $event->classDefinition->methods['setEagerLoads'] = $this->buildSetEagerLoadsMethodDefinition();
        $event->classDefinition->methods['withoutEagerLoads'] = $this->buildWithoutEagerLoadsMethodDefinition();
    }

    private function buildWithMethodDefinition(): ShallowFunctionDefinition
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
                 * @return WithProperties<
                 *     $this,
                 *     array{
                 *         eagerLoad: ArrayMerge<
                 *             PropertyFetch<$this, 'eagerLoad'>,
                 *             TCallback is Closure
                 *                 ? list{TRelations}
                 *                 : (TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>)
                 *         >
                 *     }
                 * >
                 */
                returnType: new Generic(WithProperties::class, [
                    new SelfType(''),
                    new KeyedArrayType([
                        new ArrayItemType_('eagerLoad', new Generic(ArrayMerge::class, [
                            new Generic(PropertyFetch::class, [
                                new SelfType(''),
                                new LiteralStringType('eagerLoad'),
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
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithoutMethodDefinition(): ShallowFunctionDefinition
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
                 * @return WithProperties<
                 *     $this,
                 *     array{
                 *         eagerLoad: SubtractArrays<
                 *             PropertyFetch<$this, 'eagerLoad'>,
                 *             TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
                 *         >
                 *     }
                 * >
                 */
                returnType: new Generic(WithProperties::class, [
                    new SelfType(''),
                    new KeyedArrayType([
                        new ArrayItemType_('eagerLoad', new Generic(SubtractArrays::class, [
                            new Generic(PropertyFetch::class, [
                                new SelfType(''),
                                new LiteralStringType('eagerLoad'),
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
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithOnlyMethodDefinition(): ShallowFunctionDefinition
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
                 * @return WithProperties<
                 *     $this,
                 *     array{
                 *         eagerLoad: TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
                 *     }
                 * >
                 */
                returnType: new Generic(WithProperties::class, [
                    new SelfType(''),
                    new KeyedArrayType([
                        new ArrayItemType_('eagerLoad', new ConditionalType(
                            $tRelations,
                            new StringType,
                            new TemplateType('Arguments'),
                            new Generic(EagerLoadRelationsList::class, [$tRelations]),
                        )),
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildSetEagerLoadsMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tEagerLoad = new TemplateType('TEagerLoad'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'setEagerLoads',
                arguments: [
                    'eagerLoad' => $tEagerLoad,
                ],
                /**
                 * @see Builder::setEagerLoads
                 *
                 * @return WithProperties<
                 *     $this,
                 *     array{eagerLoad: TEagerLoad}
                 * >
                 */
                returnType: new Generic(WithProperties::class, [
                    new SelfType(''),
                    new KeyedArrayType([
                        new ArrayItemType_('eagerLoad', $tEagerLoad),
                    ]),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Builder::class,
        );
    }

    private function buildWithoutEagerLoadsMethodDefinition(): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'withoutEagerLoads',
                returnType: new Generic(WithProperties::class, [
                    new SelfType(''),
                    new KeyedArrayType([
                        new ArrayItemType_('eagerLoad', new KeyedArrayType([], isList: true)),
                    ]),
                ]),
            ),
            definingClassName: Builder::class,
        );
    }
}
