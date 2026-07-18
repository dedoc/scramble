<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
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

        $items = [];

        if ($includedResources = $this->getIncludedResources($collectedResource)) {
            $items[] = new InferType\ArrayItemType_('included', $includedResources, isOptional: true);
        }

        if ($jsonApiObject = $this->getJsonApiObject()) {
            $items[] = new InferType\ArrayItemType_('jsonapi', $jsonApiObject);
        }

        return $items ? new InferType\KeyedArrayType($items) : null;
    }

    protected function getJsonApiObject(): ?InferType\KeyedArrayType
    {
        $information = JsonApiResource::$jsonApiInformation;

        if (! $information) {
            return null;
        }

        $type = TypeHelper::createTypeFromValue($information);

        return $type instanceof InferType\KeyedArrayType ? $type : null;
    }

    protected function isUserDefinedWithMethod(ObjectType $resource): bool
    {
        $resourceDefinition = $this->infer->index->getClass($resource->name);

        $withDefiningClassName = $resourceDefinition?->getMethod('with')?->definingClassName;

        return $withDefiningClassName !== JsonApiResource::class
            && $withDefiningClassName !== JsonApiAnonymousResourceCollection::class;
    }

    protected function getIncludedResources(ObjectType $resource): ?InferType\ArrayType
    {
        $relationships = ReflectionJsonApiResource::createForClass($resource->name)->getNestedRelationshipItems(
            $this->openApiContext->config->jsonApi->maxRelationshipDepth(),
        );

        if (! $relationships) {
            return null;
        }

        $included = array_map(fn ($r) => $r->resourceType, $relationships);

        return new InferType\ArrayType(InferType\Union::wrap($included));
    }

    public function getResourceType(InferType\ObjectType $type): ?ObjectType
    {
        if ($type->isInstanceOf(JsonApiAnonymousResourceCollection::class)) {
            $collectedType = ResourceCollectionTypeManager::make($type)->getCollectedType();

            return $collectedType instanceof Generic ? $collectedType : null;
        }

        return $type;
    }
}
