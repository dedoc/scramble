<?php

namespace Dedoc\Scramble\Support\RouteProviders;

use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

interface RouteProvider
{
    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection;
}
