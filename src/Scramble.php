<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\ServerFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class Scramble
{
    public static $routeResolver = null;

    public static $tagResolver = null;

    public static $openApiExtender = null;

    public static bool $defaultRoutesIgnored = false;

    /**
     * Disables registration of default Scramble's documentation routes.
     */
    public static function ignoreRoutes(): void
    {
        static::$defaultRoutesIgnored = true;
    }

    /**
     * Update open api document before finally rendering it.
     */
    public static function extendOpenApi(callable $openApiExtender)
    {
        static::$openApiExtender = $openApiExtender;
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

    public static function buildDefaultGeneratorStrategy()
    {
        return new GeneratorStrategy(
            routeResolver: static::$routeResolver ?? function (Route $route) {
                $expectedDomain = config('scramble.api_domain');

                return Str::startsWith($route->uri, config('scramble.api_path', 'api'))
                    && (! $expectedDomain || $route->getDomain() === $expectedDomain);
            },
            tagsResolver: static::$tagResolver ?? function (RouteInfo $routeInfo) {
                return array_unique([
                    ...static::extractTagsForMethod($routeInfo),
                    Str::of(class_basename($routeInfo->className()))->replace('Controller', ''),
                ]);
            },
            openApiExtender: static::$openApiExtender ?? fn ($openApi) => $openApi,
        );
    }

    private static function extractTagsForMethod(RouteInfo $routeInfo)
    {
        $classPhpDoc = $routeInfo->reflectionMethod()
            ? $routeInfo->reflectionMethod()->getDeclaringClass()->getDocComment()
            : false;

        $classPhpDoc = $classPhpDoc ? PhpDoc::parse($classPhpDoc) : new PhpDocNode([]);

        if (! count($tagNodes = $classPhpDoc->getTagsByName('@tags'))) {
            return [];
        }

        return explode(',', array_values($tagNodes)[0]->value->value);
    }

    public static function registerUiRoute(string $path, array $config = []): Route
    {
        $config = array_merge(config('scramble'), $config);

        return RouteFacade::get($path, function (Generator $generator) use ($config) {
            return view('scramble::docs', ['spec' => $generator($config)]);
        })
            ->middleware(Arr::get($config, 'middleware', [RestrictedDocsAccess::class]));
    }

    public static function registerJsonSpecificationRoute(string $path, array $config = []): Route
    {
        $config = array_merge(config('scramble'), $config);

        return RouteFacade::get($path, function (Generator $generator) {
            return response()->json($generator(), options: JSON_PRETTY_PRINT);
        })
            ->middleware(Arr::get($config, 'middleware', [RestrictedDocsAccess::class]));
    }
}
