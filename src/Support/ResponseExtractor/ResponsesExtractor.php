<?php

namespace Dedoc\Scramble\Support\ResponseExtractor;

use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Arr;

class ResponsesExtractor
{
    private RouteInfo $routeInfo;
    private TypeTransformer $transformer;

    public function __construct(RouteInfo $routeInfo, TypeTransformer $transformer)
    {
        $this->routeInfo = $routeInfo;
        $this->transformer = $transformer;
    }

    public function __invoke()
    {
        $returnTypes = $this->routeInfo->getReturnTypes();

        if (!$returnType = $returnTypes[0] ?? null) {
            return [];
        }

        return array_filter([
            $this->transformer->toResponse($returnType)
        ]);
    }
}
