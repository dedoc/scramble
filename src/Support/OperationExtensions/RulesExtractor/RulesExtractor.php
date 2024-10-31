<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\RouteInfo;

interface RulesExtractor
{
    public function shouldHandle(): bool;

    public function extract(RouteInfo $routeInfo): ParametersExtractionResult;
}
