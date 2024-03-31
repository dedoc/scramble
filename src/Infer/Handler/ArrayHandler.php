<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node;

class ArrayHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Array_;
    }

    public function leave(Node\Expr\Array_ $node, Scope $scope)
    {
        $arrayItems = collect($node->items)
            ->filter()
            ->map(fn (Node\Expr\ArrayItem $arrayItem) => $scope->getType($arrayItem))
            ->all();

        $scope->setType(
            $node,
            TypeHelper::unpackIfArray(new KeyedArrayType($arrayItems))
        );
    }
}
