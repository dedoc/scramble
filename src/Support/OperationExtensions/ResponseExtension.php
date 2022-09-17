<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

class ResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $returnTypes = $routeInfo->getReturnTypes();

        if (! $returnType = $returnTypes[0] ?? null) {
            return [];
        }

        $responses = array_filter([
            $this->openApiTransformer->toResponse($returnType),
        ]);

        foreach ($responses as $response) {
            $operation->addResponse($response);
        }
    }
}
