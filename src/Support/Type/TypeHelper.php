<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
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
    public static function getArgType(Scope $scope, array $args, array $parameterNameIndex, Type $default = null)
    {
        $default = $default ?: new UnknownType("Cannot get a type of the arg #{$parameterNameIndex[1]}($parameterNameIndex[0])");

        $matchingArg = static::getArg($args, $parameterNameIndex);

        return $matchingArg ? $scope->getType($matchingArg->value) : $default;
    }

    public static function unpackIfArrayType($type)
    {
        if (! $type instanceof ArrayType) {
            return $type;
        }

        $unpackedItems = collect($type->items)
            ->flatMap(function (ArrayItemType_ $type) {
                if ($type->shouldUnpack && $type->value instanceof ArrayType) {
                    return $type->value->items;
                }

                return [$type];
            })
            ->reduce(function ($arrayItems, ArrayItemType_ $itemType) {
                if (! $itemType->key) {
                    $arrayItems[] = $itemType;
                } else {
                    $arrayItems[$itemType->key] = $itemType;
                }

                return $arrayItems;
            }, []);

        return new ArrayType(array_values($unpackedItems));
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

    public static function createTypeFromValue(mixed $value)
    {
        if (is_string($value)) {
            return new LiteralStringType($value);
        }

        if (is_int($value)) {
            return new LiteralIntegerType($value);
        }

        if (is_float($value)) {
            return new FloatType();
        }

        if (is_bool($value)) {
            return new LiteralBooleanType($value);
        }

        return null; // @todo: object
    }
}
