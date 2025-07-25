<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use PhpParser\Node;

class ArrayItemHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\ArrayItem;
    }

    public function leave(Node\Expr\ArrayItem $node, Scope $scope)
    {
        $keyType = $node->key ? $scope->getType($node->key) : null;

        $scope->setType(
            $node,
            new ArrayItemType_(
                $keyType instanceof LiteralStringType ? $keyType->value : null, // @todo handle cases when key is something dynamic
                $scope->getType($node->value),
                isOptional: false,
                shouldUnpack: $node->unpack,
                keyType: $keyType,
            )
        );
    }
}
