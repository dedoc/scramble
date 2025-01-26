<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\TypeTransformer;

/** @internal */
class OperationBuilder
{
    /** @var class-string<OperationExtension> */
    private array $extensionsClasses;

    public function __construct(array $extensionsClasses = [])
    {
        $this->extensionsClasses = $extensionsClasses;
    }

    public function build(RouteInfo $routeInfo, OpenApi $openApi, GeneratorConfig $config, TypeTransformer $typeTransformer)
    {
        $operation = new Operation('get');

        foreach ($this->extensionsClasses as $extensionClass) {
            $extension = app()->make($extensionClass, [
                'openApi' => $openApi,
                'config' => $config,
                'openApiTransformer' => $typeTransformer,
            ]);

            $extension->handle($operation, $routeInfo);
        }

        return $operation;
    }
}
