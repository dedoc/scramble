<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\RouteProviders\FilterableRouteProvider;
use Dedoc\Scramble\Support\RouteProviders\RouteProvider;
use Illuminate\Routing\Route;

trait CreatesGenerator
{
    private function createGenerator(): Generator
    {
        $routes = $this->option('routes');
        $routeNames = is_string($routes)
            ? array_values(array_filter(
                array_map(trim(...), explode(',', $routes)),
                fn (string $routeName) => $routeName !== '',
            ))
            : [];

        return new Generator(
            app(OperationBuilder::class),
            new FilterableRouteProvider(
                app(RouteProvider::class),
                fn (Route $route) => $routeNames === [] || in_array($route->getName(), $routeNames, true),
            ),
        );
    }
}
