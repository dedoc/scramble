<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceResponse;

class ResourceResponseMethodReturnTypeExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(ResourceResponse::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->name !== 'toResponse') {
            return null;
        }

        $resourceType = $event->getInstance()->templateTypes[0] ?? null;
        if (! $resourceType) {
            return new Generic(JsonResponse::class, [new UnknownType, new UnknownType, new KeyedArrayType]);
        }

        return new Generic(JsonResponse::class, [
            new Generic(ResourceResponse::class, [$resourceType]),
            new UnknownType,
            new ArrayType,
        ]);
    }
}
