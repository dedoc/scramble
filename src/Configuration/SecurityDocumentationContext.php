<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Support\Collection;

class SecurityDocumentationContext
{
    /**
     * @param  Collection<int, \Illuminate\Routing\Route>  $routes
     */
    public function __construct(
        public readonly Collection $routes,
        public readonly GeneratorConfig $config,
    ) {}
}
