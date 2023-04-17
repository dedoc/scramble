<?php

namespace Dedoc\Scramble\Support\Generator;

class RouteDocumentation
{
    public string $method;

    public string $path;

    public Operation $operation;

    public Components $components;

    public function __construct(
        string $method,
        string $path,
        Operation $operation,
        Components $components
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->operation = $operation;
        $this->components = $components;
    }
}
