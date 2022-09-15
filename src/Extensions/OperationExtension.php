<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Type;

abstract class OperationExtension
{
    protected TypeTransformer $openApiTransformer;

    public function __construct(TypeTransformer $openApiTransformer)
    {
        $this->openApiTransformer = $openApiTransformer;
    }

    abstract public function handle(Operation $operation, RouteInfo $routeInfo);
}
