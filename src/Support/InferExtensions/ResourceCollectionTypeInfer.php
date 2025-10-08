<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ResourceCollectionTypeInfer implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(ResourceCollection::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'toArray' => $this->getToArrayReturnType($event),
            default => null,
        };
    }

    private function getToArrayReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->methodDefiningClassName === ResourceCollection::class) {
            $isManualAnnotation = $event->getInstance() instanceof Generic
                && count($event->getInstance()->templateTypes) === 1;

            if ($isManualAnnotation) {
                return $this->getCollectionType($event->getInstance(), $event->scope->index);
            }

            return null; // default behavior
        }

        $parentType = $this->getCollectionType($event->getInstance(), $event->scope->index);

        $realType = $event->getDefinition()->getMethodDefinition('toArray')?->getReturnType();
        if ($realType instanceof UnknownType) {
            /**
             * When inferred return type of `toArray` method cannot be inferred, we'd like to fall back to
             * the default behavior of ResourceCollection, so there is still SOME information.
             */
            return $parentType;
        }

        return null; // default behavior
    }

    private function getCollectionType(ObjectType $type, Index $index): ArrayType
    {
        $normalizedType = (! $type instanceof Generic) ? new Generic($type->name) : $type;

        return new ArrayType(
            (new ResourceCollectionTypeManager($normalizedType, $index))->getCollectedType(),
        );
    }
}
