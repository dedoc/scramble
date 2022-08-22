<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Types\Type;

class TypeHandlers
{
    private static $handlers = [
        IdentifierTypeNodeHandler::class,
        ArrayShapeNodeHandler::class,
        ArrayShapeItemNodeHandler::class,
        GenericTypeNodeHandler::class,
        ArrayTypeNodeHandler::class,
        IntersectionTypeNodeHandler::class,
        UnionTypeNodeHandler::class,
    ];

    /** @var array<string, callable> */
    private static $identifierHandlers = [];

    public static function handle($node): ?Type
    {
        foreach (static::$handlers as $handler) {
            if ($handler::shouldHandle($node)) {
                return (new $handler($node))->handle();
            }
        }

        return null; // @todo: unknown type with reason
    }

    public static function registerIdentifierHandler(string $id, callable $handler)
    {
        static::$identifierHandlers[$id] = $handler;
    }

    public static function unregisterIdentifierHandler(string $id)
    {
        unset(static::$identifierHandlers[$id]);
    }

    /**
     * Used to get types of the classes which are created on the app level.
     */
    public static function handleIdentifier(string $name)
    {
        foreach (static::$identifierHandlers as $handler) {
            if ($type = $handler($name)) {
                return $type;
            }
        }

        return null; // @todo: unknown type with reason
    }
}
