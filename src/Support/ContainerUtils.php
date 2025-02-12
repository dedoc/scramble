<?php

namespace Dedoc\Scramble\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

class ContainerUtils
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function makeContextable(string $class, array $contextfulBindings = [])
    {
        $reflectionClass = new ReflectionClass($class);

        $parameters = $reflectionClass->getConstructor()?->getParameters() ?? [];

        $contextfulArguments = collect($parameters)
            ->mapWithKeys(function (ReflectionParameter $p) use ($contextfulBindings) {
                $parameterClass = $p->getType() instanceof ReflectionNamedType
                    ? $p->getType()->getName()
                    : null;

                return $parameterClass && isset($contextfulBindings[$parameterClass]) ? [
                    $p->name => $contextfulBindings[$parameterClass],
                ] : [];
            })
            ->all();

        return app()->make($class, $contextfulArguments);
    }
}
