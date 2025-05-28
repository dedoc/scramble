<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

interface ParameterExtractor
{
    /**
     * @param  ParametersExtractionResult[]  $parameterExtractionResults
     * @return ParametersExtractionResult[]
     */
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array;
}
