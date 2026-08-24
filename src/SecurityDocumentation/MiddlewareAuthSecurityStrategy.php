<?php

namespace Dedoc\Scramble\SecurityDocumentation;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\SecurityDocumentationContext;
use Dedoc\Scramble\Contracts\SecurityDocumentationStrategy;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class MiddlewareAuthSecurityStrategy implements SecurityDocumentationStrategy
{
    private SecurityScheme $scheme;

    /**
     * @param  list<string>  $middleware
     */
    public function __construct(
        private array $middleware = ['auth', 'auth:*'],
        ?SecurityScheme $scheme = null,
    ) {
        $this->scheme = $scheme ?? SecurityScheme::http('bearer');
    }

    public function configure(SecurityDocumentationContext $context): GeneratorConfig
    {
        $hasAuthenticatedRoutes = $context->routes->contains(
            fn (Route $route) => $this->routeHasMiddleware($route),
        );

        if (! $hasAuthenticatedRoutes) {
            return $context->config;
        }

        return $context->config
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure($this->scheme);
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo): void {
                    if ($this->routeHasMiddleware($routeInfo->route)) {
                        return;
                    }

                    $operation->security = [];
                });
            });
    }

    private function routeHasMiddleware(Route $route): bool
    {
        return collect($route->gatherMiddleware())
            ->some(fn (string $middleware) => Str::is($this->middleware, $middleware));
    }
}
