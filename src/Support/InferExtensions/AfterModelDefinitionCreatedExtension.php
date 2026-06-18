<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\EagerLoadRelationsList;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\WithProperties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AfterModelDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
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
         * @return WithProperties<
         *     Builder<$this>,
         *     array{eagerLoad: EagerLoadRelationsList<TWith>}
         * >
         */
        return new Generic(WithProperties::class, [
            new Generic(Builder::class, [new ObjectType($definition->name)]),
            new KeyedArrayType([
                new ArrayItemType_('eagerLoad', new Generic(EagerLoadRelationsList::class, [
                    $this->resolveWithDefaultType($definition),
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
