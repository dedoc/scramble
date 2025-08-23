<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @deprecated
 */
class AnonymousResourceCollectionTypeToSchema extends TypeToSchemaExtension
{
    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(AnonymousResourceCollection::class)
            && count($type->templateTypes) > 0;
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type): ?OpenApiType
    {
        return (new ResourceCollectionTypeToSchema(
            $this->infer,
            $this->openApiTransformer,
            $this->components,
            $this->openApiContext,
        ))->toSchema($type);
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): ?Response
    {
        return (new ResourceCollectionTypeToSchema(
            $this->infer,
            $this->openApiTransformer,
            $this->components,
            $this->openApiContext,
        ))->toResponse($type);
    }
}
