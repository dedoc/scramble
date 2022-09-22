<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class Generator
{
    private TypeTransformer $transformer;

    private OperationBuilder $operationBuilder;

    public function __construct(TypeTransformer $transformer, OperationBuilder $operationBuilder)
    {
        $this->transformer = $transformer;
        $this->operationBuilder = $operationBuilder;
    }

    public function __invoke()
    {
        $openApi = $this->makeOpenApi();

        $this->getRoutes()//->dd()
            ->map(fn (Route $route) => $this->routeToOperation($route))
            ->filter() // Closure based routes are filtered out for now, right here
            ->each(fn (Operation $operation) => $openApi->addPath(
                Path::make(str_replace('api/', '', $operation->path))->addOperation($operation)
            ))
            ->toArray();

        if (isset(Scramble::$openApiExtender)) {
            (Scramble::$openApiExtender)($openApi);
        }

        return $openApi->toArray();
    }

    private function makeOpenApi()
    {
        $openApi = OpenApi::make('3.1.0')
            ->setComponents($this->transformer->getComponents())
            ->setInfo(InfoObject::make(config('app.name'))->setVersion('0.0.1'));

        $openApi->addServer(Server::make(url('/api')));

        return $openApi;
    }

    private function getRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->pipe(function (Collection $c) {
                $onlyRoute = $c->first(function (Route $route) {
                    if (! is_string($route->getAction('uses'))) {
                        return false;
                    }
                    try {
                        $reflection = new \ReflectionMethod(...explode('@', $route->getAction('uses')));

                        if (str_contains($reflection->getDocComment() ?: '', '@only-docs')) {
                            return true;
                        }
                    } catch (\Throwable $e) {
                    }

                    return false;
                });

                return $onlyRoute ? collect([$onlyRoute]) : $c;
            })
            ->filter(function (Route $route) {
                return ! ($name = $route->getAction('as')) || ! Str::startsWith($name, 'scramble');
            })
            ->filter(fn (Route $r) => isset($r->action['controller']))
            ->filter(function (Route $route) {
                $routeResolver = Scramble::$routeResolver ?? fn (Route $route) => in_array('api', $route->gatherMiddleware());

                return $routeResolver($route);
            })
            ->values();
    }

    private function routeToOperation(Route $route)
    {
        $routeInfo = new RouteInfo($route);

        if (! $routeInfo->isClassBased()) {
            return null;
        }

        return $this->operationBuilder->build($routeInfo);
    }
}
