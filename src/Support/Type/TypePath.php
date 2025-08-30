<?php

namespace Dedoc\Scramble\Support\Type;

class TypePath
{
    /**
     * @param TypePathItem[] $items
     */
    public function __construct(public array $items)
    {
    }

    public function getFrom(Type $type): Type|array|null
    {
        $typeInCheck = $type;
        foreach ($this->items as $item) {
            if(! $typeInCheck = $item->getFrom($typeInCheck)) {
                return null;
            }
        }
        return $typeInCheck;
    }

    public static function findFirst(Type $type, callable $cb): ?TypePath
    {
        $traverser = new TypeTraverser([
            $visitor = new TypePathFindingVisitor($cb),
        ]);

        $traverser->traverse($type);

        return $visitor->getPath();
    }
}
