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
use Dedoc\Scramble\Support\ServerFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Throwable;

class Generator
{
    private TypeTransformer $transformer;

    private OperationBuilder $operationBuilder;

    private ServerFactory $serverFactory;

    public function __construct(TypeTransformer $transformer, OperationBuilder $operationBuilder, ServerFactory $serverFactory)
    {
        $this->transformer = $transformer;
        $this->operationBuilder = $operationBuilder;
        $this->serverFactory = $serverFactory;
    }

    public function __invoke()
    {
        $openApi = $this->makeOpenApi();

        $this->getRoutes()
            ->map(function (Route $route) use ($openApi) {
                try {
                    return $this->routeToOperation($openApi, $route);
                } catch (Throwable $e) {
                    if (config('app.debug', false)) {
                        $method = $route->methods()[0];
                        $action = $route->getAction('uses');

                        logger()->error("Error when analyzing route '$method $route->uri' ($action): {$e->getMessage()} – ".($e->getFile().' on line '.$e->getLine()));
                    }

                    throw $e;
                }
            })
            ->filter() // Closure based routes are filtered out for now, right here
            ->each(fn (Operation $operation) => $openApi->addPath(
                Path::make(
                    (string) Str::of($operation->path)
                        ->replaceFirst(config('scramble.api_path', 'api'), '')
                        ->trim('/')
                )->addOperation($operation)
            ))
            ->toArray();

        $this->moveSameAlternativeServersToPath($openApi);

        if (isset(Scramble::$openApiExtender)) {
            (Scramble::$openApiExtender)($openApi);
        }

        return $openApi->toArray();
    }

    private function makeOpenApi()
    {
        $openApi = OpenApi::make('3.1.0')
            ->setComponents($this->transformer->getComponents())
            ->setInfo(
                InfoObject::make(config('app.name'))
                    ->setVersion(config('scramble.info.version', '0.0.1'))
                    ->setDescription(config('scramble.info.description', ''))
            );

        [$defaultProtocol] = explode('://', url('/'));
        $servers = config('scramble.servers') ?: [
            '' => ($domain = config('scramble.api_domain'))
                ? $defaultProtocol.'://'.$domain.'/'.config('scramble.api_path', 'api')
                : config('scramble.api_path', 'api'),
        ];
        foreach ($servers as $description => $url) {
            $openApi->addServer(
                $this->serverFactory->make(url($url ?: '/'), $description)
            );
        }

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
                    } catch (Throwable $e) {
                    }

                    return false;
                });

                return $onlyRoute ? collect([$onlyRoute]) : $c;
            })
            ->filter(function (Route $route) {
                return ! ($name = $route->getAction('as')) || ! Str::startsWith($name, 'scramble');
            })
            ->filter(function (Route $route) {
                $routeResolver = Scramble::$routeResolver ?? function (Route $route) {
                    $expectedDomain = config('scramble.api_domain');

                    return Str::startsWith($route->uri, config('scramble.api_path', 'api'))
                        && (! $expectedDomain || $route->getDomain() === $expectedDomain);
                };

                return $routeResolver($route);
            })
            ->filter(fn (Route $r) => $r->getAction('controller'))
            ->values();
    }

    private function routeToOperation(OpenApi $openApi, Route $route)
    {
        $routeInfo = new RouteInfo($route);

        if (! $routeInfo->isClassBased()) {
            return null;
        }

        return $this->operationBuilder->build($routeInfo, $openApi);
    }

    private function moveSameAlternativeServersToPath(OpenApi $openApi)
    {
        foreach (collect($openApi->paths)->groupBy('path') as $pathsGroup) {
            if ($pathsGroup->isEmpty()) {
                continue;
            }

            $operations = collect($pathsGroup->pluck('operations')->flatten());
            $operationsHaveSameAlternativeServers = $operations->count()
                && $operations->every(fn (Operation $o) => count($o->servers))
                && $operations->unique(function (Operation $o) {
                    return collect($o->servers)->map(fn (Server $s) => $s->url)->join('.');
                })->count() === 1;

            if (! $operationsHaveSameAlternativeServers) {
                continue;
            }

            $pathsGroup->every->servers($operations->first()->servers);

            foreach ($operations as $operation) {
                $operation->servers([]);
            }
        }
    }
}
