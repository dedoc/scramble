<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EloquentBuilderExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Builder::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->getDefinition()->hasMethodDefinition($event->getName())) {
            return null;
        }

        if ($this->shouldForwardCallToModel($event)) {
            return $this->forwardCallToModel($event);
        }

        return null;
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
