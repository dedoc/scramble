<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @see JsonApiResourceCollection
 * @see JsonApiResource
 *
 * @mixin JsonApiResourceResponseToSchemaExtension|JsonApiPaginatedResourceResponseToSchemaExtension
 */
trait HandlesJsonApiResourceResponse
{
    protected function getWithType(ObjectType $type): ?InferType\KeyedArrayType
    {
        if ($this->isUserDefinedWithMethod($type)) {
            return parent::getWithType($type);
        }

        if (! $collectedResource = $this->getResourceType($type)) {
            return null;
        }

        if (! $includedResources = $this->getIncludedResources($collectedResource)) {
            return null;
        }

        return new InferType\KeyedArrayType([
            new InferType\ArrayItemType_('included', $includedResources, isOptional: true),
        ]);
    }

    protected function isUserDefinedWithMethod(ObjectType $resource): bool
    {
        $resourceDefinition = $this->infer->index->getClass($resource->name);

        $withDefiningClassName = $resourceDefinition?->getMethod('with')?->definingClassName;

        return $withDefiningClassName !== JsonApiResource::class
            //&& $withDefiningClassName !== JsonApiResourceCollection::class
            ;
    }

    protected function getIncludedResources(ObjectType $resource): ?InferType\ArrayType
    {
        if (! $relationships = ReflectionJsonApiResource::createForClass($resource->name)->getRelationshipsType()) {
            return null;
        }

        $included = [];
        foreach ($relationships->items as $item) {
            if ($item->value->isInstanceOf(AnonymousResourceCollection::class)) {
                $included[] = ResourceCollectionTypeManager::make($item->value)->getCollectedType();
            } elseif ($item->value->isInstanceOf(JsonApiResource::class)) {
                $included[] = $item->value;
            }
        }

        if (! $included) {
            return null;
        }

        return new InferType\ArrayType(InferType\Union::wrap($included));
    }

    public function getResourceType(InferType\ObjectType $type): ?ObjectType
    {
        if ($type->isInstanceOf(JsonApiResourceCollection::class)) {
            $collectedType = ResourceCollectionTypeManager::make($type)->getCollectedType();

            return $collectedType instanceof Generic ? $collectedType : null;
        }

        return $type;
    }
}
