<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;

class AnonymousResourceCollectionTypeToSchema extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use MergesOpenApiObjects;

    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(AnonymousResourceCollection::class)
            && count($type->templateTypes) > 0;
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        if (! $collectingResourceType = $this->getCollectingResourceType($type)) {
            return null;
        }

        return (new OpenApiArrayType)
            ->setItems($this->openApiTransformer->transform($collectingResourceType));
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        $additional = $type->templateTypes[1 /* TAdditional */] ?? new UnknownType;
        if ($additional instanceof KeyedArrayType) {
            $additional->items = $this->flattenMergeValues($additional->items);
        }

        if (! $collectingResourceType = $this->getCollectingResourceType($type)) {
            return null;
        }

        $shouldWrap = ($wrapKey = $collectingResourceType->name::$wrap ?? null) !== null
            || $additional instanceof KeyedArrayType;
        $wrapKey = $wrapKey ?: 'data';

        // In case of paginated resource, we want to get pagination response.
        if ($type->templateTypes[0] instanceof Generic && ! $type->templateTypes[0]->isInstanceOf(JsonResource::class)) {
            return $this->getPaginatedCollectionResponse($type->templateTypes[0], $collectingResourceType, $wrapKey);
        }

        $jsonResourceOpenApiType = $this->openApiTransformer->transform($collectingResourceType);

        $openApiType = $shouldWrap
            ? (new OpenApiObjectType)
                ->addProperty($wrapKey, (new OpenApiArrayType)->setItems($jsonResourceOpenApiType))
                ->setRequired([$wrapKey])
            : (new OpenApiArrayType)->setItems($jsonResourceOpenApiType);

        if ($shouldWrap) {
            if ($additional instanceof KeyedArrayType) {
                $this->mergeOpenApiObjects($openApiType, $this->openApiTransformer->transform($additional));
            }
        }

        return Response::make(200)
            ->description('Array of `'.$this->components->uniqueSchemaName($collectingResourceType->name).'`')
            ->setContent('application/json', Schema::fromType($openApiType));
    }

    /**
     * @see PaginatedResourceResponse
     */
    private function getPaginatedCollectionResponse(Generic $type, ObjectType $collectingClassType, string $wrapKey)
    {
        if (! $type->isInstanceOf(AbstractPaginator::class) && ! $type->isInstanceOf(AbstractCursorPaginator::class)) {
            return null;
        }

        $paginatorResponse = $this->openApiTransformer->toResponse($type);
        $paginatorSchema = array_values($paginatorResponse->content)[0] ?? null;
        $paginatorSchemaType = $paginatorSchema->type ?? null;

        if (! $paginatorSchemaType instanceof OpenApiObjectType) {
            // should not happen
            return null;
        }

        $responseType = (new OpenApiObjectType)
            ->addProperty($wrapKey, $paginatorSchemaType->properties['data'])
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
            ->setRequired([$wrapKey, 'links', 'meta']);

        return Response::make(200)
            ->description('Paginated set of `'.$this->components->uniqueSchemaName($collectingClassType->name).'`')
            ->setContent('application/json', Schema::fromType($responseType));
    }

    private function getCollectingResourceType(Generic $type): ?ObjectType
    {
        // In case of paginated resource, we still want to get to the underlying JsonResource.
        return (new TypeWalker)->first(
            $type->templateTypes[0],
            fn (Type $t) => $t->isInstanceOf(JsonResource::class),
        );
    }
}
