<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;

class ResourceCollectionTypeToSchema extends JsonResourceTypeToSchema
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ResourceCollection::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        if ($this->isPaginatedResource($type)) {
            $resourceResponseType = new Generic(PaginatedResourceResponse::class, [$type]);

            return (new PaginatedResourceResponseTypeToSchema($this->infer, $this->openApiTransformer, $this->components, $this->openApiContext))
                ->toResponse($resourceResponseType);
        }

        return parent::toResponse($type);
    }

    private function isPaginatedResource(ObjectType $type): bool
    {
        if (! $type instanceof Generic) {
            return false;
        }

        $resourceType = $type->templateTypes[/* TResource */ 0] ?? null;
        if (! $resourceType instanceof ObjectType) {
            return false;
        }

        return $resourceType->isInstanceOf(AbstractPaginator::class)
            || $resourceType->isInstanceOf(AbstractCursorPaginator::class);
    }

    public function reference(ObjectType $type)
    {
        if (! $this->shouldReferenceResourceCollection($type)) {
            return null;
        }

        return parent::reference($type);
    }

    public function shouldReferenceResourceCollection(ObjectType $type): bool
    {
        return ! Str::contains(class_basename($type->name), 'anonymous', ignoreCase: true);
    }
}
