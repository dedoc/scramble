<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayMerge;
use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\EagerLoadRelationsList;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PropertyFetch;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
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
}
