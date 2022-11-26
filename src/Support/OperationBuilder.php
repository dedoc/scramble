<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;

class OperationBuilder
{
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function build(RouteInfo $routeInfo, OpenApi $openApi)
    {
        $operation = new Operation('get');

        foreach ($this->extensions as $extension) {
            $extension->handle($operation, $routeInfo, $openApi);
        }

        return $operation;
    }
}
