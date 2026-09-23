<?php

namespace Dedoc\Scramble\Console\Commands\Concerns;

use Dedoc\Scramble\Contracts\RouteProvider;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\RouteProviders\FilterableRouteProvider;
use Illuminate\Routing\Route;

trait CreatesGenerator
{
    private function createGenerator(?RouteProvider $routeProvider = null): Generator
    {
        return new Generator(
            app(OperationBuilder::class),
            $routeProvider ?? $this->createRouteProvider(),
        );
    }

    private function createRouteProvider(): FilterableRouteProvider
    {
        $routes = $this->option('routes');
        $routeNames = is_string($routes)
            ? array_values(array_filter(
                array_map(trim(...), explode(',', $routes)),
                fn (string $routeName) => $routeName !== '',
            ))
            : [];

        return new FilterableRouteProvider(
            app(RouteProvider::class),
            fn (Route $route) => $routeNames === [] || in_array($route->getName(), $routeNames, true),
        );
    }
}
