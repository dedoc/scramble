<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;

class OperationBuilder
{
    /** @var class-string<OperationExtension> */
    private array $extensionsClasses;

    public function __construct(array $extensionsClasses = [])
    {
        $this->extensionsClasses = $extensionsClasses;
    }

    public function build(RouteInfo $routeInfo, OpenApi $openApi)
    {
        $operation = new Operation('get');

        foreach ($this->extensionsClasses as $extensionClass) {
            $extension = app()->make($extensionClass, [
                'openApi' => $openApi,
            ]);

            $extension->handle($operation, $routeInfo);
        }

        return $operation;
    }
}
