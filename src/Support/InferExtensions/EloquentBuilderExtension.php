<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentBuilderExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Builder::class)
            || $type->isInstanceOf(Relation::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->getInstance()->isInstanceOf(Relation::class)) {
            return $this->forwardRelationCallToRelatedModelBuilder($event);
        }

        if ($event->getDefinition()->hasMethodDefinition($event->getName())) {
            return $this->handleExistingMethodReturnType($event);
        }

        if ($this->shouldForwardCallToModel($event)) {
            return $this->forwardCallToModel($event);
        }

        if (! $modelType = $this->getModelType($event->getInstance())) {
            return null;
        }
        if (
            $this->modelIsSoftDeletable($modelType)
            && ($softDeleteCallResult = $this->handleSoftDeletes($event, $modelType))
        ) {
            return $softDeleteCallResult;
        }

        return null;
    }

    private function forwardRelationCallToRelatedModelBuilder(MethodCallEvent $event): ?Type
    {
        // Methods defined on Relation itself must use the relation definition. In
        // particular, Scramble adds relation-aware definitions for with(), etc.
        if ($event->getDefinition()?->hasMethodDefinition($event->getName())) {
            return null;
        }

        $relationType = $this->normalizeType($event->getInstance());
        $relatedModelType = $relationType->templateTypes[0] ?? null;

        if (! $relatedModelType instanceof ObjectType || ! $relatedModelType->isInstanceOf(Model::class)) {
            return null;
        }

        $builderType = new Generic(
            ModelBuilderTypeResolver::resolveClass($relatedModelType->name),
            [$relatedModelType],
        );

        if (! $event->scope->index->getClass($builderType->name)?->hasMethodDefinition($event->getName())) {
            return null;
        }

        $returnType = $builderType->getMethodReturnType(
            $event->getName(),
            $event->arguments,
            $event->scope,
        );

        return $this->replaceBuilderReturnWithRelation($returnType, $event->getInstance());
    }

    private function replaceBuilderReturnWithRelation(Type $returnType, ObjectType $relationType): Type
    {
        if ($returnType instanceof Union) {
            return Union::wrap(array_map(
                fn (Type $type) => $this->replaceBuilderReturnWithRelation($type, $relationType),
                $returnType->types,
            ));
        }

        return $returnType->isInstanceOf(Builder::class)
            ? $relationType
            : $returnType;
    }

    private function handleExistingMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'get' => ($modelType = $this->getModelType($event->getInstance()))
                ? ModelCollectionTypeResolver::resolve($modelType)
                : null,
            'find' => $this->getFindReturnType($event),
            'findOrFail' => $this->getFindOrFailReturnType($event),
            default => null,
        };
    }

    private function getFindReturnType(MethodCallEvent $event): ?Type
    {
        if (! $modelType = $this->getModelType($event->getInstance())) {
            return null;
        }

        if ($this->isListOrArrayableId($event->getArg('id', 0))) {
            return ModelCollectionTypeResolver::resolve($modelType);
        }

        return Union::wrap([$modelType, new NullType]);
    }

    private function getFindOrFailReturnType(MethodCallEvent $event): ?Type
    {
        if (! $modelType = $this->getModelType($event->getInstance())) {
            return null;
        }

        if ($this->isListOrArrayableId($event->getArg('id', 0))) {
            return ModelCollectionTypeResolver::resolve($modelType);
        }

        return $modelType;
    }

    private function isListOrArrayableId(Type $idType): bool
    {
        if ($idType instanceof KeyedArrayType || $idType instanceof ArrayType) {
            return true;
        }

        return $idType->isInstanceOf(Arrayable::class);
    }

    private function shouldForwardCallToModel(MethodCallEvent $event): bool
    {
        if (! $modelType = $this->getModelType($event->getInstance())) {
            return false;
        }

        return $event->scope->index->getClass($modelType->name)?->hasMethodDefinition(
            $this->getScopeMethodName($event->name),
        ) ?? false;
    }

    private function forwardCallToModel(MethodCallEvent $event): ?Type
    {
        if (! $modelType = $this->getModelType($event->getInstance())) {
            return null;
        }

        return $event->getInstance();
    }

    /**
     * @see SoftDeletes
     */
    private function modelIsSoftDeletable(ObjectType $modelType): bool
    {
        return method_exists($modelType->name, 'bootSoftDeletes');
    }

    private function handleSoftDeletes(MethodCallEvent $event, ObjectType $modelType): ?Type
    {
        return match ($event->name) {
            'onlyTrashed', 'withTrashed', 'withoutTrashed' => $event->getInstance(),
            'restoreOrCreate', 'createOrRestore' => $modelType,
            default => null,
        };
    }

    private function getModelType(ObjectType $instance): ?ObjectType
    {
        $type = $this->normalizeType($instance);

        $modelType = $type->templateTypes[0] ?? null;

        if (! $modelType instanceof ObjectType) {
            return null;
        }

        if (! $modelType->isInstanceOf(Model::class)) {
            return null;
        }

        return $modelType;
    }

    private function normalizeType(ObjectType $type): Generic
    {
        if ($type instanceof Generic) {
            return $type;
        }

        return new Generic($type->name, []);
    }

    private function getScopeMethodName(string $scope): string
    {
        return 'scope'.ucfirst($scope);
    }
}
