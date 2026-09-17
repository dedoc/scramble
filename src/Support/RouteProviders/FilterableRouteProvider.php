<?php

namespace Dedoc\Scramble\Support\RouteProviders;

use Closure;
use Dedoc\Scramble\Contracts\RouteProvider;
use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

class FilterableRouteProvider implements RouteProvider
{
    /**
     * @param  Closure(Route): bool  $filter
     */
    public function __construct(
        private RouteProvider $routeProvider,
        private Closure $filter,
    ) {}

    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection
    {
        return $this->routeProvider
            ->get($config)
            ->filter($this->filter)
            ->values();
    }
}
