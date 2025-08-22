<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Analyzer\MethodQuery;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

class PaginatedResourceResponseTypeToSchema extends ResourceResponseTypeToSchema
{

    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(PaginatedResourceResponse::class)
            && count($type->templateTypes) >= 1;
    }

    /**
     * @param Generic $type
     */
    public function toResponse(Type $type): Response
    {
        $response = parent::toResponse(
            $this->prepareRawCollectionType($type),
        );

        // Transform paginator instance!

        $this->applyPaginatedData($type, $response);

//        $this->applyPaginatedDescription($response);

        return $response;
    }

    private function applyPaginatedData(Generic $type, Response $response): void
    {
        $paginatedData = $this->getPaginatedData($type);

        $content = $response->getContent('application/json')->type;

        if (! $content instanceof OpenApiObjectType) {
            return;
        }

        $this->mergeOpenApiObjects(
            $content,
            $paginatedData,
        );
    }

    private function getPaginatedData(Generic $type): OpenApiObjectType
    {
        $paginatorSchemaType = $this->getPaginatorSchemaType($type);

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
            ->setRequired(['links', 'meta']);
    }

    private function prepareRawCollectionType(Generic $type): Generic
    {
        // The case when collection is manually annotated
        // @todo
        $collectionType = $type->templateTypes[/* TData */ 0]->clone();
        // assert generic
        $collectionType->templateTypes[0] = $this->unwrapPaginatorType($collectionType->templateTypes[0]);

        return new Generic(ResourceResponse::class, [$collectionType]);
    }

    private function unwrapPaginatorType(Type $paginatorType)
    {
        $valueType = $paginatorType->templateTypes[1] ?? $paginatorType->templateTypes[0] ?? new UnknownType;

        return $valueType;
    }

    private function getPaginatorSchemaType(Generic $type): OpenApiObjectType
    {
        $paginatorResponse = $this->getPaginatorResponse($type);

        $paginatorSchema = array_values($paginatorResponse->content)[0] ?? null;
        $paginatorSchemaType = $paginatorSchema?->type;

        if (! $paginatorSchemaType instanceof OpenApiObjectType) {
            throw new LogicException("Paginator schema is expected to be an object.");
        }

        return $paginatorSchemaType;
    }

    private function getPaginatorResponse(Generic $type): Response
    {
        $collectingClassType = $this->getCollectingClassType($type);
        $paginatorType = $type->templateTypes[/* TData */ 0]->templateTypes[/* TResource */ 0] ?? null;

        if (! $paginatorType instanceof ObjectType) {
            throw new InvalidArgumentException("Paginator type is expected to be ObjectType");
        }

        if (! $paginatorType instanceof Generic) {
            $paginatorType = new Generic($paginatorType->name);
        }

        if (count($paginatorType->templateTypes) === 2) {
            $paginatorType = $paginatorType->clone();
            $paginatorType->templateTypes[1] = $collectingClassType;
        }

        $paginatorResponse = $this->openApiTransformer->toResponse($paginatorType);
        if (! $paginatorResponse instanceof Response) {
            throw new LogicException("{$paginatorType->toString()} is expected to produce Response instance when casted to response.");
        }

        return $paginatorResponse;
    }

    private function getCollectingClassType(Generic $type): Generic|UnknownType
    {
        return (new ResourceCollectionTypeManager($type->templateTypes[0], $this->infer->index))->getCollectedType();
    }
}
