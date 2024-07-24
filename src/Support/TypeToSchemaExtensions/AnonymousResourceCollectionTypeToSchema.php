<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

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

        // In case of paginated resource, we want to get pagination response.
        if ($type->templateTypes[0] instanceof Generic && ! $type->templateTypes[0]->isInstanceOf(JsonResource::class)) {
            return $this->openApiTransformer->toResponse($type->templateTypes[0]);
        }

        if (! $collectingResourceType = $this->getCollectingResourceType($type)) {
            return null;
        }

        $jsonResourceOpenApiType = $this->openApiTransformer->transform($collectingResourceType);

        $shouldWrap = ($wrapKey = AnonymousResourceCollection::$wrap ?? null) !== null
            || $additional instanceof KeyedArrayType;
        $wrapKey = $wrapKey ?: 'data';

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

    private function getCollectingResourceType(Generic $type): ?ObjectType
    {
        // In case of paginated resource, we still want to get to the underlying JsonResource.
        return (new TypeWalker)->first(
            $type->templateTypes[0],
            fn (Type $t) => $t->isInstanceOf(JsonResource::class),
        );
    }
}
