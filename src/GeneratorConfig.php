<?php

namespace Dedoc\Scramble;

use Closure;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GeneratorConfig
{
    /**
     * @var (Closure(Router, mixed): Route)|string|null
     */
    public Closure|string|null $uiRoute = null;

    /**
     * @var (Closure(Router, mixed): Route)|string|null
     */
    public Closure|string|null $documentRoute = null;

    public function __construct(
        private array $config = [],
        private ?Closure $routeResolver = null,
        private array $afterOpenApiGenerated = [],
        public readonly ParametersExtractors $parametersExtractors = new ParametersExtractors,
    ) {}

    public function config(array $config)
    {
        $this->config = $config;

        return $this;
    }

    public function routes(?Closure $routeResolver = null)
    {
        if (count(func_get_args()) === 0) {
            return $this->routeResolver ?: $this->defaultRoutesFilter(...);
        }

        if ($routeResolver) {
            $this->routeResolver = $routeResolver;
        }

        return $this;
    }

    /**
     * @param (Closure(Router, mixed): Route)|string|false $ui
     * @param (Closure(Router, mixed): Route)|string|false $document
     * @return static
     */
    public function expose(Closure|string|false $ui = false, Closure|string|false $document = false): static
    {
        if (count(func_get_args()) === 1 && isset(func_get_args()[0]) && func_get_args()[0] === false) {
            $this->uiRoute = null;
            $this->documentRoute = null;

            return $this;
        }

        $this->uiRoute = $ui ?: null;
        $this->documentRoute = $document ?: null;

        return $this;
    }

    private function defaultRoutesFilter(Route $route)
    {
        $expectedDomain = $this->get('api_domain');

        return Str::startsWith($route->uri, $this->get('api_path', 'api'))
            && (! $expectedDomain || $route->getDomain() === $expectedDomain);
    }

    public function afterOpenApiGenerated(?callable $afterOpenApiGenerated = null)
    {
        if (count(func_get_args()) === 0) {
            return $this->afterOpenApiGenerated;
        }

        if ($afterOpenApiGenerated) {
            $this->afterOpenApiGenerated[] = $afterOpenApiGenerated;
        }

        return $this;
    }

    public function useConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function withParametersExtractors(callable $callback): static
    {
        $callback($this->parametersExtractors);

        return $this;
    }

    public function get(string $key, mixed $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }
}
