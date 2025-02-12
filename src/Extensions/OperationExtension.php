<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;

abstract class OperationExtension implements OperationTransformer
{
    public function __construct(
        protected Infer $infer,
        protected TypeTransformer $openApiTransformer,
        protected GeneratorConfig $config
    ) {}

    abstract public function handle(Operation $operation, RouteInfo $routeInfo);
}
