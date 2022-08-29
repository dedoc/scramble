<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class NewHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\New_;
    }

    public function leave(Node\Expr\New_ $node)
    {
        if (! ($node->class instanceof Node\Name)) {
            return null;
        }

        $node->setAttribute(
            'type',
            new ObjectType($node->class->toString()),
        );
    }
}
