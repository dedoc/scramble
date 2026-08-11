<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
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
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\WithProperties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

class AfterModelDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension, StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return is_a($name, Model::class, true);
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->methods['query'] = $this->buildQueryMethodDefinition($definition);
        $definition->methods['newQuery'] = $this->buildNewQueryMethodDefinition($definition);
        $definition->methods['load'] = $this->buildLoadMethodDefinition($definition);
        $definition->methods['loadMissing'] = $this->buildLoadMissingMethodDefinition($definition);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if (! $definition = $event->scope->index->getClass($event->callee)) {
            return null;
        }

        if (in_array(Searchable::class, class_uses_recursive($event->callee), true)) {
            return match ($event->name) {
                'search' => new Generic(ScoutBuilder::class, [
                    $this->buildSeededModelType($definition),
                ]),
                default => null,
            };
        }

        return null;
    }

    private function buildQueryMethodDefinition(ClassDefinition $definition): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'query',
                returnType: $this->buildSeededBuilderReturnType($definition),
            ),
            definingClassName: $definition->name,
            isStatic: true,
        );
    }

    private function buildNewQueryMethodDefinition(ClassDefinition $definition): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'newQuery',
                returnType: $this->buildSeededBuilderReturnType($definition),
            ),
            definingClassName: $definition->name,
        );
    }

    private function buildSeededBuilderReturnType(ClassDefinition $definition): Generic
    {
        /**
         * @see Model::query
         * @see Model::newQuery
         *
         * @return Builder<
         *     WithProperties<
         *         $this,
         *         array{relations: EagerLoadRelationsList<TWith>}
         *     >
         * >
         */
        return new Generic(ModelBuilderTypeResolver::resolveClass($definition->name), [
            $this->buildSeededModelType($definition),
        ]);
    }

    private function buildSeededModelType(ClassDefinition $definition): Generic
    {
        return new Generic(WithProperties::class, [
            new ObjectType($definition->name),
            new KeyedArrayType([
                new ArrayItemType_('relations', new Generic(EagerLoadRelationsList::class, [
                    $this->resolveWithDefaultType($definition),
                ])),
            ]),
        ]);
    }

    private function buildLoadMethodDefinition(ClassDefinition $definition): ShallowFunctionDefinition
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
            definingClassName: $definition->name,
            selfOutType: $this->buildLoadRelationsReturnType($tRelations),
        );
    }

    private function buildLoadMissingMethodDefinition(ClassDefinition $definition): ShallowFunctionDefinition
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
            definingClassName: $definition->name,
            selfOutType: $this->buildLoadRelationsReturnType($tRelations),
        );
    }

    private function buildLoadRelationsReturnType(TemplateType $tRelations): Generic
    {
        $selfType = new SelfType('');

        /**
         * @see Model::load
         * @see Model::loadMissing
         *
         * @return WithProperties<
         *     $this,
         *     array{
         *         relations: ArrayMerge<
         *             PropertyFetch<$this, 'relations'>,
         *             TRelations is string ? Arguments : EagerLoadRelationsList<TRelations>
         *         >
         *     }
         * >
         */
        return new Generic(WithProperties::class, [
            $selfType,
            new KeyedArrayType([
                new ArrayItemType_('relations', new Generic(ArrayMerge::class, [
                    new Generic(PropertyFetch::class, [
                        $selfType,
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

    private function resolveWithDefaultType(ClassDefinition $definition): Type
    {
        $withProperty = $definition->getPropertyDefinition('with');

        $defaultType = $withProperty?->defaultType;

        if (! $defaultType instanceof KeyedArrayType) {
            return new KeyedArrayType([]);
        }

        return $defaultType;
    }
}
