<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Type\ArrayType;
use PhpParser\Node;

class ArrayHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Array_;
    }

    public function leave(Node\Expr\Array_ $node)
    {
        $arrayItems = collect($node->items)
            ->filter()
            ->map(function (Node\Expr\ArrayItem $arrayItem) {
                return $arrayItem->getAttribute('type');
            })
            ->all();

        $node->setAttribute('type', new ArrayType($arrayItems));
    }
}
