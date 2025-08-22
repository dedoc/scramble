<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
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

        $response = parent::toResponse($type);

        if (! $this->shouldReferenceResourceCollection($type)) {
            $this->addShapeDescription($type, $response);
        }

        return $response;
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

    private function addShapeDescription(ObjectType $type, Response $response): void
    {
        $type = (! $type instanceof Generic) ? new Generic($type->name) : $type;

        $collectedResourceType = (new ResourceCollectionTypeManager($type, $this->infer->index))->getCollectedType();

        if (! $collectedResourceType instanceof ObjectType) {
            return;
        }

        $response->setDescription('Array of `'.$this->openApiContext->references->schemas->uniqueName($collectedResourceType->name).'`');
    }

    public function reference(ObjectType $type)
    {
        if (! $this->shouldReferenceResourceCollection($type)) {
            return null;
        }

        return parent::reference($type);
    }

    protected function shouldReferenceResourceCollection(ObjectType $type): bool
    {
        return ! Str::contains(class_basename($type->name), 'anonymous', ignoreCase: true);
    }
}
