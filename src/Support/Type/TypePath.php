<?php

namespace Dedoc\Scramble\Support\Type;

use Closure;

class TypePath
{
    /**
     * @param  TypePathItem[]  $items
     */
    public function __construct(public array $items) {}

    /**
     * @return Type|Type[]|null
     */
    public function getFrom(Type $type): Type|array|null
    {
        $typeInCheck = $type;
        foreach ($this->items as $item) {
            if (! $typeInCheck = $item->getFrom($typeInCheck)) {
                return null;
            }
        }

        return $typeInCheck;
    }

    /**
     * @param  Closure(Type): bool  $cb
     */
    public static function findFirst(Type $type, Closure $cb): ?TypePath
    {
        $traverser = new TypeTraverser([
            $visitor = new TypePathFindingVisitor($cb),
        ]);

        $traverser->traverse($type);

        return $visitor->getPath();
    }
}
