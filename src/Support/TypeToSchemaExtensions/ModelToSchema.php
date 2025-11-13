<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Not used, just for backward compatibility.
 */
class ModelToSchema extends TypeToSchemaExtension
{
    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(Model::class)
            && $type->name !== Model::class;
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        return $this->createArrayableExtension()->toSchema($type);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        return $this->createArrayableExtension()->toResponse($type);
    }

    public function reference(ObjectType $type)
    {
        return $this->createArrayableExtension()->reference($type);
    }

    private function createArrayableExtension(): ArrayableToSchema
    {
        return new ArrayableToSchema(
            $this->infer,
            $this->openApiTransformer,
            $this->components,
            $this->openApiContext,
        );
    }
}
