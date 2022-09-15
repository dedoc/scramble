<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Support\Generator\Operation;

class OperationBuilder
{
    private array $extensions;

    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function build(Operation $operation, RouteInfo $routeInfo)
    {
        foreach ($this->extensions as $extension) {
            $operation = $extension->handle($operation, $routeInfo);
        }
    }
}
