<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\RouteInfo;

class RequestMethodCallsExtractor implements RulesExtractor
{
    public function shouldHandle(): bool
    {
        return true;
    }

    public function extract(RouteInfo $routeInfo): ParametersExtractionResult
    {
        return new ParametersExtractionResult(
            parameters: array_values($routeInfo->requestParametersFromCalls->data),
        );
    }
}
