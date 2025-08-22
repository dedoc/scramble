<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\InferExtensions\ResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

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
        $collectedResourceType = (new ResourceCollectionTypeInfer)->getCollectedInstanceType($type);

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

    private function shouldReferenceResourceCollection(ObjectType $type): bool
    {
        $definition = $this->infer->analyzeClass($type->name);

        return (new ResourceCollectionTypeInfer)->getCollectingClassType($definition) instanceof LiteralStringType;
    }

    private function getCollectionType(ObjectType $type): ?ArrayType
    {
        $definition = $this->infer->analyzeClass($type->name);

        $array = (new ResourceCollectionTypeInfer)->getBasicCollectionType($definition);
        if ($array instanceof ArrayType) {
            return $array;
        }

        if (! $type instanceof Generic) {
            return null;
        }
        $collectName = $type->templateTypes[2 /* TCollects */];
        if (! $collectName instanceof LiteralStringType) {
            return null;
        }

        $className = $collectName->value;
        if (! is_a($className, JsonResource::class, true)) {
            return null;
        }

        return new ArrayType(new Generic($className, [new UnknownType]));
    }
}
