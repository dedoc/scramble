<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class ArrayItemHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\ArrayItem;
    }

    public function leave(Node\Expr\ArrayItem $node)
    {
        $node->setAttribute(
            'type',
            new ArrayItemType_(
                $node->key->value ?? null,
                $node->value->getAttribute('type', new UnknownType()),
                $isOptional = false,
            )
        );
    }
}
