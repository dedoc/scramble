<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\RouteInfo;

interface ParameterExtractor
{
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array;
}
