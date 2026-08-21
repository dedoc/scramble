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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $method = $this->primaryMethod();
        $detail = $this->routeAction();

        return [
            'key' => 'route:'.$method.':'.$this->uri.':'.$detail,
            'type' => 'route',
            'label' => '/'.ltrim($this->uri, '/'),
            'method' => $method,
            'detail' => $detail,
        ];
    }

    private function routeAction(): ?string
    {
        if (! $this->action) {
            return null;
        }

        if (count($parts = explode('@', $this->action)) !== 2 || ! method_exists(...$parts)) {
            return null;
        }

        [$class, $method] = $parts;
        $class = str_replace(['App\\Http\\Controllers\\', 'App\\Http\\'], '', $class);

        return "{$class}@{$method}";
    }
}
