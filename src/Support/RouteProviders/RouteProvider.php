<?php

namespace Dedoc\Scramble\Support\RouteProviders;

use Dedoc\Scramble\Attributes\Api;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Contracts\RouteProvider as RouteProviderContract;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use LogicException;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * @internal
 */
class RouteProvider implements RouteProviderContract
{
    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->pipe(function (Collection $c) {
                $onlyRoutes = $c->filter(function (Route $route) {

                    if (! is_string($route->getAction('controller'))) {
                        return false;
                    }

                    if (! is_string($route->getAction('uses'))) {
                        return false;
                    }

                    try {
                        $reflection = new ReflectionMethod(...explode('@', $route->getAction('uses')));

                        if (str_contains($reflection->getDocComment() ?: '', '@only-docs')) {
                            return true;
                        }
                    } catch (Throwable) {
                    }

                    return false;
                });

                return $onlyRoutes->count() ? $onlyRoutes : $c;
            })
            ->filter(function (Route $route) {
                return ! ($name = $route->getName()) || ! Str::startsWith($name, 'scramble');
            })
            ->filter($config->routes())
            ->filter(function (Route $route) use ($config) {
                if (! is_string($route->getAction('uses'))) {
                    return true;
                }

                try {
                    $reflection = new ReflectionMethod(...explode('@', $route->getAction('uses')));
                } catch (ReflectionException) {
                    /*
                     * If route is registered but route method doesn't exist, it will not be included
                     * in the resulting documentation.
                     */
                    return false;
                }

                if (count($reflection->getAttributes(ExcludeRouteFromDocs::class))) {
                    return false;
                }

                if (count($reflection->getDeclaringClass()->getAttributes(ExcludeAllRoutesFromDocs::class))) {
                    return false;
                }

                $apiNames = $this->getApiAttributeNames($reflection);
                if ($apiNames !== null && ! in_array($config->name, $apiNames, true)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return list<string>|null `null` when the route has no #[Api] restriction
     */
    private function getApiAttributeNames(ReflectionMethod $reflection): ?array
    {
        $attributes = $reflection->getAttributes(Api::class);

        if (! count($attributes)) {
            $attributes = $reflection->getDeclaringClass()->getAttributes(Api::class);
        }

        if (! count($attributes)) {
            return null;
        }

        $apiNames = $attributes[0]->newInstance()->only;

        $this->ensureRegisteredApiNames($apiNames);

        return $apiNames;
    }

    /**
     * @param  list<string>  $apiNames
     */
    private function ensureRegisteredApiNames(array $apiNames): void
    {
        $registeredApis = array_keys(Scramble::getConfigurationsInstance()->all());

        foreach ($apiNames as $apiName) {
            if (! in_array($apiName, $registeredApis, true)) {
                throw new LogicException("$apiName API is not registered. Register the API using `Scramble::registerApi` first.");
            }
        }
    }
}
