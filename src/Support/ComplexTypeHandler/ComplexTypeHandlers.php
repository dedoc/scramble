<?php

namespace Dedoc\Scramble\Support\ComplexTypeHandler;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Type\Identifier;

class ComplexTypeHandlers
{
    private static $handlers = [
        JsonResourceHandler::class,
        AnonymousResourceCollectionHandler::class,
        LengthAwarePaginatorHandler::class,
    ];

    public static ?Components $components = null;

    public static function handle(\Dedoc\Scramble\Support\Type\Type $type): ?Type
    {
        if (isset(static::$components) && static::$components && $type instanceof Identifier && static::$components->hasSchema($type->name)) {
            return static::$components->getSchemaReference($type->name);
        }

        $handlers = array_merge(static::$handlers, Scramble::$customComplexTypesHandlers);

        /*
         * This flag is needed, so later when JsonHandler is used, we will add a resource
         * to the OpenAPI components and reuse it as a reference.
         */
        $isJsonResourceHandlerUsed = false;

        $openApiType = null;

        foreach ($handlers as $handler) {
            if (! $handler::shouldHandle($type)) {
                continue;
            }

            if (! $resolvedType = (new $handler($type))->handle($openApiType)) {
                continue;
            }

            $openApiType = $resolvedType;

            $isJsonResourceHandlerUsed = $isJsonResourceHandlerUsed || $handler === JsonResourceHandler::class;
        }

        if ($openApiType && $isJsonResourceHandlerUsed && isset(static::$components)) {
            $openApiType = static::$components->addSchema($type->name, Schema::fromType($openApiType));
        }

        return $openApiType;
    }

    public static function registerComponentsRepository(Components $components)
    {
        static::$components = $components;
    }
}
