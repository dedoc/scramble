<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Resolves a group hierarchy from the route definition itself, using the route
 * prefix first and falling back to the dotted route name.
 *
 * Namespace-based resolution is intentionally avoided: it relies on
 * application-specific conventions and produces unpredictable results for
 * package or modular controllers.
 */
class RouteGroupResolver implements GroupResolverStrategy
{
    public function resolve(Operation $operation, Route $route): ?array
    {
        return $this->fromPrefix($route)
            ?? $this->fromName($route);
    }

    /**
     * @return list<string>|null
     */
    private function fromPrefix(Route $route): ?array
    {
        $prefix = trim((string) $route->getPrefix(), '/');
        if ($prefix === '') {
            return null;
        }

        // Strip the leading api/ and version (v1, v2, ...) segments.
        $prefix = (string) preg_replace('#^(?:api/)?(?:v\d+/)?#i', '', $prefix);

        return $this->studlySegments(explode('/', $prefix));
    }

    /**
     * @return list<string>|null
     */
    private function fromName(Route $route): ?array
    {
        $name = (string) $route->getName();
        if ($name === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('.', $name), fn ($p) => $p !== ''));
        if (count($parts) <= 1) {
            return null;
        }

        // Drop the trailing action segment (index, store, ...).
        array_pop($parts);

        return $this->studlySegments($parts);
    }

    /**
     * @param  array<int, string>  $segments
     * @return list<string>|null
     */
    private function studlySegments(array $segments): ?array
    {
        $path = array_values(array_map(
            fn (string $segment) => (string) Str::of($segment)->studly(),
            array_filter($segments, fn (string $segment) => trim($segment) !== ''),
        ));

        return $path === [] ? null : $path;
    }
}
