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

        foreach ($handlers as $handler) {
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
