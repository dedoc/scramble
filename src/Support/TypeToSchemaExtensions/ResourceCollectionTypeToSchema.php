<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

class ResourceCollectionTypeToSchema extends JsonResourceTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ResourceCollection::class);
    }

    protected function normalizeType(ObjectType $type): Generic
    {
        return $type instanceof Generic
            ? $type
            : new Generic($type->name, [
                new UnknownType,
                new UnknownType,
                ResourceCollectionTypeManager::make($type)->getCollectedType(),
            ]);
    }

    protected function getResponseType(ObjectType $type): Type
    {
        return ResourceCollectionTypeManager::make(
            $this->normalizeType($type)
        )->getResponseType();
    }

    public function reference(ObjectType $type): ?Reference
    {
        if (! $this->shouldReferenceResourceCollection($type)) {
            return null;
        }

        return parent::reference($type);
    }

    /**
     * @internal
     */
    public function shouldReferenceResourceCollection(ObjectType $type): bool
    {
        return ! Str::contains(class_basename($type->name), 'anonymous', ignoreCase: true)
            && ! $type->isInstanceOf(AnonymousResourceCollection::class);
    }
}
