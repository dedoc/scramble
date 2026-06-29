<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;

interface GroupResolverStrategy
{
    /**
     * Resolve the group path (top-down hierarchy of group names) for the given
     * operation and route. Return null when this strategy cannot resolve a path,
     * allowing the next strategy in the pipeline to attempt resolution.
     *
     * @return list<string>|null
     */
    public function resolve(Operation $operation, Route $route): ?array;
}
