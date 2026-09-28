<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

/**
 * @internal
 */
interface RouteProvider
{
    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection;
}
