<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Type;

abstract class TypeToSchemaExtension
{
    public function __construct(protected Infer $infer, protected TypeTransformer $openApiTransformer, protected Components $components) {}

    /**
     * @param  Type  $type  The type being transformed to schema.
     * @param  ?OpenApiType  $previousExtensionResult  The resulting schema from a previous extension.
     */
    public function toSchema(Type $type)
    {
        return new UnknownType;
    }

    /**
     * @param  Type  $type  The type being transformed to response.
     * @param  ?Response  $previousExtensionResult  The resulting response from a previous extension.
     */
    public function toResponse(Type $type)
    {
        return null;
    }
}
