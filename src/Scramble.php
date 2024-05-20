<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\ServerFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use LogicException;

class Scramble
{
    public static $routeResolver = null;

    public static $tagResolver = null;

    public static $openApiExtender = null;

    public static bool $defaultRoutesIgnored = false;

    /**
     * Registered APIs for which Scramble generates documentation. The key is API name
     * and the value is API's configuration.
     *
     * @var array<string, GeneratorConfig>
     */
    public static array $apis = [];

    /**
     * Disables registration of default API documentation routes.
     */
    public static function ignoreDefaultRoutes(): void
    {
        static::$defaultRoutesIgnored = true;
    }

    public static function registerApi(string $name, array $config = []): GeneratorConfig
    {
        static::$apis[$name] = $generatorConfig = new GeneratorConfig(
            config: array_merge(config('scramble'), $config),
        );

        return $generatorConfig;
    }

    /**
     * Update open api document before finally rendering it.
     */
    public static function extendOpenApi(callable $openApiExtender)
    {
        static::$openApiExtender = $openApiExtender;
    }

    /**
     * Update open api document before finally rendering it.
     */
    public static function afterOpenApiGenerated(callable $afterOpenApiGenerated)
    {
        static::$openApiExtender = $afterOpenApiGenerated;
    }

    public static function routes(callable $routeResolver)
    {
        static::$routeResolver = $routeResolver;
    }

    /**
     * Modify tag generation behaviour
     *
     * @param  callable(RouteInfo, Operation): string[]  $tagResolver
     */
    public static function resolveTagsUsing(callable $tagResolver)
    {
        static::$tagResolver = $tagResolver;
    }

    /**
     * @param  array<string, ServerVariable>  $variables
     */
    public static function defineServerVariables(array $variables)
    {
        app(ServerFactory::class)->variables($variables);
    }

    public static function registerUiRoute(string $path, string $api = 'default'): Route
    {
        $config = static::getGeneratorConfig($api);

        return RouteFacade::get($path, function (Generator $generator) use ($api) {
            $config = static::getGeneratorConfig($api);

            return view('scramble::docs', [
                'spec' => $generator($config),
                'config' => $config,
            ]);
        })
            ->middleware($config->get('middleware', [RestrictedDocsAccess::class]));
    }

    public static function registerJsonSpecificationRoute(string $path, string $api = 'default'): Route
    {
        $config = static::getGeneratorConfig($api);

        return RouteFacade::get($path, function (Generator $generator) use ($api) {
            $config = static::getGeneratorConfig($api);

            return response()->json($generator($config), options: JSON_PRETTY_PRINT);
        })
            ->middleware($config->get('middleware', [RestrictedDocsAccess::class]));
    }

    public static function getGeneratorConfig(string $api)
    {
        if (! array_key_exists($api, Scramble::$apis)) {
            throw new LogicException("$api API is not registered. Register the API using `Scramble::registerApi` first.");
        }

        return Scramble::$apis[$api];
    }
}
