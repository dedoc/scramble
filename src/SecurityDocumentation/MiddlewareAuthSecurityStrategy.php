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
    public SecurityScheme $scheme;

    /**
     * @param  list<string>  $publicUnlessMiddleware
     */
    public function __construct(
        public string $triggerMiddleware = 'auth:sanctum',
        public array $publicUnlessMiddleware = ['auth', 'auth:*'],
        ?SecurityScheme $scheme = null,
    ) {
        $this->scheme = $scheme ?? SecurityScheme::http('bearer');
    }

    public function configure(SecurityDocumentationContext $context): GeneratorConfig
    {
        $hasAuth = $context->routes->contains(
            fn (Route $route) => in_array($this->triggerMiddleware, $route->gatherMiddleware()),
        );

        if (! $hasAuth) {
            return $context->config;
        }

        return $context->config
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure($this->scheme);
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo): void {
                    $hasAnyAuthMiddleware = collect($routeInfo->route->gatherMiddleware())
                        ->some(fn ($m) => Str::is($this->publicUnlessMiddleware, $m));

                    if ($hasAnyAuthMiddleware) {
                        return;
                    }

                    $operation->security = [];
                });
            });
    }
}
