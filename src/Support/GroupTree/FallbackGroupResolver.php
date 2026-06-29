<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Terminal resolver that always returns a group path, guaranteeing the pipeline
 * never resolves to an empty hierarchy. It prefers the controller base name
 * (without the "Controller" suffix) and falls back to a single configurable
 * default group.
 */
class FallbackGroupResolver implements GroupResolverStrategy
{
    public function __construct(
        private readonly string $default = 'General',
    ) {}

    public function resolve(Operation $operation, Route $route): array
    {
        $controller = $route->getAction('controller');

        if (is_string($controller) && $controller !== '') {
            $class = class_basename(explode('@', $controller)[0]);
            $name = (string) Str::of($class)->replace('Controller', '')->trim();

            if ($name !== '') {
                return [$name];
            }
        }

        return [$this->default];
    }
}
