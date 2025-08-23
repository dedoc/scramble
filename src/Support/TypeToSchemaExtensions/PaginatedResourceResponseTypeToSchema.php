<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Support\Arr;
use LogicException;

class PaginatedResourceResponseTypeToSchema extends ResourceResponseTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(PaginatedResourceResponse::class)
            && count($type->templateTypes) >= 1
            && $type->templateTypes[0] instanceof Generic; // Collection
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): Response
    {
        $resourceType = $this->getResourceType($type);

        return $this
            ->makeResponse($resourceType)
            ->setDescription($this->getPaginatedDescription($type))
            ->setContent('application/json', Schema::fromType($this->wrap(
                $this->wrapper($this->getCollectingClassType($type)),
                $this->getCollectionSchema($type),
                $this->getMergedAdditionalSchema($type),
            )));
    }

    private function getCollectionSchema(Generic $type): OpenApiType
    {
        // When transforming response's collection, we can get either a reference,
        // or an array of schema (if collection is anonymous).
        return $this->openApiTransformer->transform($this->getResourceType($type));
    }

    private function getMergedAdditionalSchema(Generic $type): OpenApiObjectType
    {
        $resourceType = $this->getResourceType($type);

        $paginationInformation = $this->getPaginatedInformationSchema($type);
        $with = $this->getWithSchema($resourceType);
        $additional = $this->getAdditionalSchema($resourceType);

        if ($with) {
            $this->mergeOpenApiObjects($paginationInformation, $with);
        }

        if ($additional) {
            $this->mergeOpenApiObjects($paginationInformation, $additional);
        }

        return $paginationInformation;
    }

    protected function getPaginatedInformationSchema(Generic $type): OpenApiObjectType
    {
        $normalizedPaginatorType = $this->getPaginatorType($type);

        $paginatorSchemaType = $this->openApiTransformer->transform($normalizedPaginatorType);
        if (! $paginatorSchemaType instanceof OpenApiObjectType) {
            throw new LogicException('Paginator schema is expected to be an object, got '.$paginatorSchemaType::class);
        }

        return (new OpenApiObjectType)
            ->addProperty('links', tap(new OpenApiObjectType, function (OpenApiObjectType $type) use ($paginatorSchemaType) {
                $defaultLinkType = (new StringType)->nullable(true);
                $type->properties = [
                    'first' => $paginatorSchemaType->properties['first_page_url'] ?? $defaultLinkType,
                    'last' => $paginatorSchemaType->properties['last_page_url'] ?? $defaultLinkType,
                    'prev' => $paginatorSchemaType->properties['prev_page_url'] ?? $defaultLinkType,
                    'next' => $paginatorSchemaType->properties['next_page_url'] ?? $defaultLinkType,
                ];
                $type->setRequired(array_keys($type->properties));
            }))
            ->addProperty('meta', tap(new OpenApiObjectType, function (OpenApiObjectType $type) use ($paginatorSchemaType) {
                $type->properties = Arr::except($paginatorSchemaType->properties, [
                    'data',
                    'first_page_url',
                    'last_page_url',
                    'prev_page_url',
                    'next_page_url',
                ]);
                $type->setRequired(array_keys($type->properties));
            }))
            ->setRequired(['meta', 'links']);
    }

    protected function getPaginatedDescription(Generic $type): string
    {
        $collectedType = $this->getCollectingClassType($type);

        if ($collectedType instanceof UnknownType) {
            return 'Paginated set';
        }

        return 'Paginated set of `'.$this->openApiContext->references->schemas->uniqueName($collectedType->name).'`';
    }

    private function getCollectingClassType(Generic $type): Generic|UnknownType
    {
        return (new ResourceCollectionTypeManager($this->getResourceType($type), $this->infer->index))->getCollectedType();
    }

    private function getResourceType(Generic $type): Generic
    {
        $resourceType = $type->templateTypes[/* TData */ 0] ?? null;

        if (! $resourceType instanceof Generic) {
            throw new \InvalidArgumentException('Resource type of the response must be Generic, got '.($resourceType ? $resourceType::class : 'null'));
        }

        return $resourceType;
    }

    private function getPaginatorType(Generic $type): Generic
    {
        $paginatorType = $this->getResourceType($type)->templateTypes[/* TResource */ 0];

        if (! $paginatorType instanceof ObjectType) {
            throw new LogicException('Paginator type must be an object, got '.$paginatorType->toString());
        }

        return new Generic($paginatorType->name, [
            new IntegerType,
            $this->getCollectingClassType($type),
        ]);
    }
}
