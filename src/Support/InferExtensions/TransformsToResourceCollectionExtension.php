<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Attributes\UseResourceCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\TransformsToResourceCollection;
use ReflectionClass;
use Throwable;

class TransformsToResourceCollectionExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType|string $type): bool
    {
        $type = is_string($type) ? $type : $type->name;

        if (! class_exists($type) || ! method_exists($type, 'toResourceCollection')) {
            return false;
        }

        return in_array(TransformsToResourceCollection::class, class_uses_recursive($type));
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'guessResourceCollection' => $this->getGuessResourceCollectionMethodReturnType($event),
            'toResourceCollection' => $this->getToResourceCollectionMethodReturnType($event),
            default => null,
        };
    }

    protected function getToResourceCollectionMethodReturnType(MethodCallEvent $event): ?Type
    {
        $resourceClassArg = $event->getArg('resourceClass', 0);

        if ($resourceClassArg instanceof LiteralString) {
            return $this->makeResourceCollection($resourceClassArg->getValue(), $event->getInstance());
        }

        return $this->getGuessResourceCollectionMethodReturnType($event);
    }

    protected function getGuessResourceCollectionMethodReturnType(MethodCallEvent $event): ?Type
    {
        $modelClass = $this->getModelClassFromCollection($event->getInstance());

        if (! $modelClass) {
            return null;
        }

        $collectionClass = $this->resolveResourceCollectionFromAttribute($modelClass);
        if ($collectionClass && class_exists($collectionClass)) {
            /** @see AfterResourceCollectionDefinitionCreatedExtension */
            return new Generic($collectionClass, [new ObjectType($modelClass)]);
        }

        $resourceClass = $this->resolveResourceFromAttribute($modelClass);
        if ($resourceClass && class_exists($resourceClass)) {
            return $this->makeResourceCollection($resourceClass, $event->getInstance());
        }

        try {
            /** @var array<string> $candidates */
            $candidates = $modelClass::guessResourceName();

            foreach ($candidates as $candidate) {
                $collectionCandidate = $candidate.'Collection';
                if (class_exists($collectionCandidate)) {
                    return new Generic($collectionCandidate, [new ObjectType($modelClass)]);
                }
            }

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && class_exists($candidate)) { // @phpstan-ignore function.alreadyNarrowedType
                    return $this->makeResourceCollection($candidate, $event->getInstance());
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function getModelClassFromCollection(ObjectType $collectionType): ?string
    {
        if (! $collectionType instanceof Generic) {
            return null;
        }

        $itemType = $collectionType->templateTypes[1] ?? null;

        if (! $itemType instanceof ObjectType) {
            return null;
        }

        return $itemType->isInstanceOf(Model::class) ? $itemType->name : null;
    }

    private function makeResourceCollection(string $resourceClass, ObjectType $collection): ?ObjectType
    {
        $result = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new StaticMethodCallReferenceType($resourceClass, 'collection', [$collection])
            );

        if (! $result instanceof ObjectType) {
            return null;
        }

        return $result;
    }

    protected function resolveResourceFromAttribute(string $modelClassName): ?string
    {
        if (! class_exists(UseResource::class)) {
            return null;
        }

        if (! class_exists($modelClassName)) {
            return null;
        }

        $attributes = (new ReflectionClass($modelClassName))->getAttributes(UseResource::class);

        return $attributes !== []
            ? $attributes[0]->newInstance()->class
            : null;
    }

    protected function resolveResourceCollectionFromAttribute(string $modelClassName): ?string
    {
        if (! class_exists(UseResourceCollection::class)) {
            return null;
        }

        if (! class_exists($modelClassName)) {
            return null;
        }

        $attributes = (new ReflectionClass($modelClassName))->getAttributes(UseResourceCollection::class);

        return $attributes !== []
            ? $attributes[0]->newInstance()->class
            : null;
    }
}
