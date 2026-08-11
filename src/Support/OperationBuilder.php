<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use InvalidArgumentException;

/** @internal */
class OperationBuilder
{
    public function build(
        RouteInfo $routeInfo,
        OpenApi $openApi,
        GeneratorConfig $config,
        TypeTransformer $typeTransformer,
        ProNudgeCollector $proNudge,
    ) {
        $operation = new Operation('get');

        foreach ($config->operationTransformers->all() as $operationTransformerClass) {
            $instance = is_callable($operationTransformerClass)
                ? $operationTransformerClass
                : ContainerUtils::makeContextable($operationTransformerClass, [
                    OpenApi::class => $openApi,
                    OpenApiContext::class => $typeTransformer->context,
                    GeneratorConfig::class => $config,
                    TypeTransformer::class => $typeTransformer,
                    ProNudgeCollector::class => $proNudge,
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
