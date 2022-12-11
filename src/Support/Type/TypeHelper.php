<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\Scope;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class TypeHelper
{
    public static function mergeTypes(...$types)
    {
        $types = collect($types)
            ->flatMap(fn ($type) => $type instanceof Union ? $type->types : [$type])
            ->unique(fn (Type $type) => $type->toString())
            ->pipe(function (Collection $c) {
                if ($c->count() > 1 && $c->contains(fn ($t) => $t instanceof VoidType)) {
                    return $c->reject(fn ($t) => $t instanceof VoidType);
                }

                return $c;
            })
            ->all();

        return Union::wrap($types);
    }

    public static function createTypeFromTypeNode(Node $typeNode)
    {
        if ($typeNode instanceof Node\NullableType) {
            return Union::wrap([
                new NullType(),
                static::createTypeFromTypeNode($typeNode->type),
            ]);
        }

        if ($typeNode instanceof Node\Identifier) {
            if ($typeNode->name === 'int') {
                return new IntegerType();
            }

            if ($typeNode->name === 'string') {
                return new StringType();
            }

            if ($typeNode->name === 'bool') {
                return new BooleanType();
            }

            if ($typeNode->name === 'float') {
                return new FloatType();
            }

            return new ObjectType($typeNode->toString());
        }

        if ($typeNode instanceof Node\Name) {
            return new ObjectType($typeNode->toString());
        }

        if ($typeNode instanceof Node\UnionType) {
            return Union::wrap(array_map(
                fn ($node) => static::createTypeFromTypeNode($node),
                $typeNode->types
            ));
        }

        return new UnknownType('Cannot get type from AST node '.(new Standard())->prettyPrint([$typeNode]));
    }

    /**
     * @param  Node\Arg[]  $args
     * @param  array{0: string, 1: int}  $parameterNameIndex
     */
    public static function getArgType(Scope $scope, array $args, array $parameterNameIndex, ?Type $default = null)
    {
        $default = $default ?: new UnknownType("Cannot get a type of the arg #{$parameterNameIndex[1]}($parameterNameIndex[0])");

        $matchingArg = static::getArg($args, $parameterNameIndex);

        return $matchingArg ? $scope->getType($matchingArg->value) : $default;
    }

    /**
     * @param  Node\Arg[]  $args
     * @param  array{0: string, 1: int}  $parameterNameIndex
     */
    private static function getArg(array $args, array $parameterNameIndex)
    {
        [$name, $index] = $parameterNameIndex;

        return collect($args)->first(
            fn ($arg) => ($arg->name->name ?? '') === $name,
            fn () => empty($args[$index]->name->name) ? ($args[$index] ?? null) : null,
        );
    }
}
