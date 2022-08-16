<?php

namespace Dedoc\Documentor\Support\TypeHandlers;

use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class PhpDocTypeWalker
{
    public static function traverse(TypeNode $type, array $visitors)
    {
        $callVisitors = function ($node, $method) use ($visitors) {
            foreach ($visitors as $visitor) {
                $visitor->$method($node);
            }
        };

        if ($type instanceof IdentifierTypeNode) {
            $callVisitors($type, 'enter');
            $callVisitors($type, 'leave');
        }

        if ($type instanceof GenericTypeNode) {
            $callVisitors($type, 'enter');
            static::traverse($type->type, $visitors);
            foreach ($type->genericTypes as $genericType) {
                static::traverse($genericType, $visitors);
            }
            $callVisitors($type, 'leave');
        }
    }
}
