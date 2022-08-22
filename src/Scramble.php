<?php

namespace Dedoc\Scramble;

class Scramble
{
    public static $operationResolver;

    public static $openApiExtender;

    public static $routeResolver;

    public static array $customComplexTypesHandlers = [];

    public static function resolveOperationUsing(callable $operationResolver)
    {
        static::$operationResolver = $operationResolver;
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

    public static function registerCustomComplexTypeHandler(string $handlerClassName)
    {
        static::$customComplexTypesHandlers[] = $handlerClassName;
    }
}
