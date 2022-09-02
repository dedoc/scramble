<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Infer\Infer;

abstract class TypeToOpenApiSchemaExtension
{
    protected Infer $infer;
    protected TypeTransformer $openApiTransformer;
    protected Components $components;

    public function __construct(Infer $infer, TypeTransformer $openApiTransformer, Components $components)
    {
        $this->infer = $infer;
        $this->openApiTransformer = $openApiTransformer;
        $this->components = $components;
    }
}
