<?php

namespace Dedoc\Scramble\Diagnostics;

use Illuminate\Routing\Route;

class RouteContext
{
    /**
     * @param  non-empty-list<string>  $methods
     */
    public function __construct(
        public array $methods,
        public string $uri,
        public ?string $action,
    ) {}

    public static function fromRoute(Route $route): self
    {
        $methods = array_values($route->methods());
        $action = $route->getAction('uses');

        return new self(
            methods: $methods ?: ['GET'],
            uri: $route->uri(),
            action: is_string($action) ? $action : null,
        );
    }

    public function primaryMethod(): string
    {
        return collect($this->methods)->first(fn (string $method) => $method !== 'HEAD')
            ?? $this->methods[0];
    }

    public function controllerClass(): ?string
    {
        return $this->action ? explode('@', $this->action, 2)[0] : null;
    }
}
