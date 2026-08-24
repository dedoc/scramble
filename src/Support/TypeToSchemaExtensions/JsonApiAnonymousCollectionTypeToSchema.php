<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\InferExtensions\JsonApiResourceCollectionMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
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

    /**
     * @see JsonApiResourceCollectionMethodReturnTypeExtension::getMethodReturnType()
     */
    protected function getResponseType(ObjectType $type): Type
    {
        return ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new MethodCallReferenceType($type, 'toResponse', [])
            );
    }
}
