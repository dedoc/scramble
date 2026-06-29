<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ConfigGroupResolver implements GroupResolverStrategy
{
    public function resolve(Operation $operation, Route $route): ?array
    {
        /** @var array<int, array<string, mixed>> $rules */
        $rules = config('scramble.groups.rules', []);

        foreach ($rules as $rule) {
            if (! isset($rule['group'], $rule['match'])) {
                continue;
            }

            if (! is_array($rule['match']) || ! is_string($rule['group'])) {
                continue;
            }

            if ($this->routeMatchesRules($route, $rule['match'])) {
                // Supports nested groups, e.g. 'Admin/Security'.
                $path = GroupPathSplitter::split($rule['group']);

                if ($path !== []) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Determine if the route matches the configured rules.
     *
     * @param  array<array-key, mixed>  $rules
     */
    protected function routeMatchesRules(Route $route, array $rules): bool
    {
        // 1. Match by middleware
        if (isset($rules['middleware'])) {
            $routeMiddlewares = $route->gatherMiddleware();
            $matchMiddlewares = Arr::wrap($rules['middleware']);

            foreach ($matchMiddlewares as $pattern) {
                if (! is_string($pattern)) {
                    continue;
                }
                foreach ($routeMiddlewares as $middleware) {
                    $middlewareName = is_string($middleware) ? $middleware : $middleware::class;
                    if (Str::is($pattern, $middlewareName)) {
                        return true;
                    }
                }
            }
        }

        // 2. Match by route prefix / URI path pattern
        if (isset($rules['prefix'])) {
            $patterns = Arr::wrap($rules['prefix']);
            $uri = $route->uri();
            foreach ($patterns as $pattern) {
                if (! is_string($pattern)) {
                    continue;
                }
                if (Str::is($pattern, $uri) || Str::is($pattern, (string) $route->getPrefix())) {
                    return true;
                }
            }
        }

        // 3. Match by controller namespace
        if (isset($rules['namespace'])) {
            $patterns = Arr::wrap($rules['namespace']);
            $controller = $route->getAction('controller');
            if (is_string($controller)) {
                foreach ($patterns as $pattern) {
                    if (is_string($pattern) && Str::is($pattern, $controller)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
