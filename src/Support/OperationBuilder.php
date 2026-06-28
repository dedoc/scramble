<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/** @internal */
class OperationBuilder
{
    /**
     * @return Operation[]
     */
    public function buildAll(OpenApiContext $context, Route $route, TypeTransformer $typeTransformer): array
    {
        $methods = array_map('strtolower', Arr::wrap(($context->config->operationMethodsResolver)($route)));

        $operations = [];
        foreach ($methods as $method) {
            $routeInfo = new RouteInfo($route, $method);

            $operation = $this->build($routeInfo, $context->openApi, $context->config, $typeTransformer);

            $operations[] = $operation;
        }

        return $operations;
    }

    public function build(RouteInfo $routeInfo, OpenApi $openApi, GeneratorConfig $config, TypeTransformer $typeTransformer): Operation
    {
        $operation = new Operation('get');

        foreach ($config->operationTransformers->all() as $operationTransformerClass) {
            $instance = is_callable($operationTransformerClass)
                ? $operationTransformerClass
                : ContainerUtils::makeContextable($operationTransformerClass, [
                    OpenApi::class => $openApi,
                    OpenApiContext::class => $typeTransformer->context,
                    GeneratorConfig::class => $config,
                    TypeTransformer::class => $typeTransformer,
                    DiagnosticsCollector::class => $typeTransformer->context->diagnostics->forRoute($routeInfo->route),
                ]);

            if (is_callable($instance)) {
                $instance($operation, $routeInfo);

                continue;
            }

            if ($instance instanceof OperationTransformer) {
                $instance->handle($operation, $routeInfo);

                continue;
            }

            // @phpstan-ignore deadCode.unreachable
            throw new InvalidArgumentException('(callable(Operation, RouteInfo): void)|OperationTransformer type for operation transformer expected, received '.$instance::class);
        }

        return $operation;
    }
}
