<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Type;

abstract class ExceptionToResponseExtension
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

    public function toResponse(Type $type)
    {
        return null;
    }
}
