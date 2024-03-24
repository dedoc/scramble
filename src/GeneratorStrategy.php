<?php

namespace Dedoc\Scramble;

use Closure;

class GeneratorStrategy
{
    private array $config = [];

    public function __construct(
        private Closure $routeResolver,
        private Closure $tagsResolver,
        private Closure $openApiExtender,
    ) {
    }

    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }
}
