<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PhpParser\Node;

class ArrayItemHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\ArrayItem;
    }

    public function leave(Node\Expr\ArrayItem $node, Scope $scope)
    {
        $scope->setType(
            $node,
            new ArrayItemType_(
                $node->key ? ($scope->getType($node->key)->value ?? null) : null, // @todo handle cases when key is something dynamic
                $scope->getType($node->value),
                isOptional: false,
                shouldUnpack: $node->unpack,
            )
        );
    }
}
