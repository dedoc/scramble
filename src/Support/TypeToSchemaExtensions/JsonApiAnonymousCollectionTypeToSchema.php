<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;

/**
 * @see JsonApiAnonymousResourceCollection
 */
class JsonApiAnonymousCollectionTypeToSchema extends ResourceCollectionTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(JsonApiAnonymousResourceCollection::class);
    }

    protected function getResponseType(ObjectType $type): Type
    {
        return new Generic(JsonResponse::class, [
            parent::getResponseType($type),
            new InferType\UnknownType,
            new InferType\KeyedArrayType([
                new InferType\ArrayItemType_('Content-type', new InferType\Literal\LiteralStringType('application/vnd.api+json')),
            ]),
        ]);
    }
}
