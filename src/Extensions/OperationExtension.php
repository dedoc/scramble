<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Support\Generator\RouteDocumentation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;

abstract class OperationExtension
{
    protected Infer $infer;

    protected TypeTransformer $openApiTransformer;

    public function __construct(Infer $infer, TypeTransformer $openApiTransformer)
    {
        $this->infer = $infer;
        $this->openApiTransformer = $openApiTransformer;
    }

    abstract public function handle(RouteDocumentation $documentation, RouteInfo $routeInfo);
}
