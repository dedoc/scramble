<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Generator\ServerVariable;
use Dedoc\Scramble\Support\ServerFactory;

class Scramble
{
    public static $openApiExtender;

    public static $routeResolver;

    public static $tagResolver;

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
     * Modify scramble's tag behaviour
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
}
