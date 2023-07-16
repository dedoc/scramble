<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
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

    abstract public function handle(Operation $operation, RouteInfo $routeInfo);
}
