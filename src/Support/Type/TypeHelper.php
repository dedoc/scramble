<?php

namespace Dedoc\Scramble\Support\Type;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class TypeHelper
{
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
