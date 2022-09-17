<?php

namespace Dedoc\Scramble;

class Scramble
{
    public static $openApiExtender;

    public static $routeResolver;

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
}
