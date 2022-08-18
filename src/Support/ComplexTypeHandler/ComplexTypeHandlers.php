<?php

namespace Dedoc\ApiDocs\Support\ComplexTypeHandler;

use Dedoc\ApiDocs\Support\Generator\Components;
use Dedoc\ApiDocs\Support\Generator\Schema;
use Dedoc\ApiDocs\Support\Generator\Types\Type;
use Dedoc\ApiDocs\Support\Type\Identifier;

class ComplexTypeHandlers
{
    private static $handlers = [
        JsonResourceHandler::class,
        AnonymousResourceCollectionHandler::class,
        LengthAwarePaginatorHandler::class,
    ];

    public static Components $components;

    public static function handle(\Dedoc\ApiDocs\Support\Type\Type $type): ?Type
    {
        if (isset(static::$components) && $type instanceof Identifier && static::$components->hasSchema($type->name)) {
            return static::$components->getSchemaReference($type->name);
        }

        foreach (static::$handlers as $handler) {
            if (! $handler::shouldHandle($type)) {
                continue;
            }

            if (! $resolvedType = (new $handler($type))->handle()) {
                continue;
            }

            if ($handler === JsonResourceHandler::class && isset(static::$components)) {
                return static::$components->addSchema($type->name, Schema::fromType($resolvedType));
            }

            return $resolvedType;
        }

        return null; // @todo: unknown type with reason
    }

    public static function registerComponentsRepository(Components $components)
    {
        static::$components = $components;
    }
}
