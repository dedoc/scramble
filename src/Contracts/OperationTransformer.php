<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

interface OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo);
}
