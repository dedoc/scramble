<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;

abstract class OperationExtension
{
    protected TypeTransformer $openApiTransformer;

    public function __construct(TypeTransformer $openApiTransformer)
    {
        $this->openApiTransformer = $openApiTransformer;
    }

    abstract public function handle(Operation $operation, RouteInfo $routeInfo);
}
