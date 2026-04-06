<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class JsonApiResourceResponseToSchemaExtension extends ResourceResponseTypeToSchema
{
    use HandlesJsonApiResourceResponse;

    public function shouldHandle(Type $type): bool
    {
        return parent::shouldHandle($type)
            && (
                $type->templateTypes[0]->isInstanceOf(JsonApiResource::class)
                || $type->templateTypes[0]->isInstanceOf(JsonApiAnonymousResourceCollection::class)
            );
    }
}
