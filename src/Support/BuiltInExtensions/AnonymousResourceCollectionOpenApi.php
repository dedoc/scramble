<?php

namespace Dedoc\Scramble\Support\BuiltInExtensions;

use Dedoc\Scramble\Extensions\TypeToOpenApiSchemaExtension;
use Illuminate\Http\Resources\Json\{AnonymousResourceCollection, JsonResource};
use Dedoc\Scramble\Support\Generator\{Response, Schema, Types\ArrayType as OpenApiArrayType, Types\ObjectType as OpenApiObjectType};
use Dedoc\Scramble\Support\Type\{Generic, ObjectType, Type, TypeWalker};

class AnonymousResourceCollectionOpenApi extends TypeToOpenApiSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(AnonymousResourceCollection::class)
            && count($type->genericTypes) === 1;
    }

    public function toSchema(Generic $type)
    {
        if (!$collectingResourceType = $this->getCollectingResourceType($type)) {
            return null;
        }

        return (new OpenApiArrayType)
            ->setItems($this->openApiTransformer->transform($collectingResourceType));
    }

    public function toResponse(Generic $type)
    {
        // In case of paginated resource, we want to get pagination response.
        if ($type->genericTypes[0] instanceof Generic) {
            return $this->openApiTransformer->toResponse($type->genericTypes[0]);
        }

        if (!$collectingResourceType = $this->getCollectingResourceType($type)) {
            return null;
        }

        $jsonResourceOpenApiType = $this->openApiTransformer->transform($collectingResourceType);
        $responseWrapKey = ($collectingResourceType->name)::$wrap;

        $type = $responseWrapKey
            ? (new OpenApiObjectType)->addProperty($responseWrapKey, $jsonResourceOpenApiType)->setRequired([$responseWrapKey])
            : (new OpenApiArrayType)->setItems($jsonResourceOpenApiType);

        return Response::make(200)
            ->description('Array of `'.$this->components->uniqueSchemaName($collectingResourceType->name).'`')
            ->setContent('application/json', Schema::fromType($type));
    }

    private function getCollectingResourceType(Generic $type): ?ObjectType
    {
        // In case of paginated resource, we still want to get to the underlying JsonResource.
        return TypeWalker::first(
            $type->genericTypes[0],
            fn (Type $t) => $t->isInstanceOf(JsonResource::class),
        );
    }
}
