<?php

namespace Dedoc\Scramble\Support\Type;

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
            return new Identifier($typeNode->toString());
        }

        if ($typeNode instanceof Node\Name) {
            return new Identifier($typeNode->toString());
        }

        if ($typeNode instanceof Node\UnionType) {
            return Union::wrap(array_map(
                fn ($node) => static::createTypeFromTypeNode($node),
                $typeNode->types
            ));
        }

        return new UnknownType('Cannot get type from AST node '.(new Standard())->prettyPrint([$typeNode]));
    }
}
