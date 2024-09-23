<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

interface RulesExtractor
{
    public function shouldHandle(): bool;

    public function extract(RouteInfo $routeInfo): ParametersExtractionResult;
}
