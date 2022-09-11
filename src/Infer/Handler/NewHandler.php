<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class NewHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\New_;
    }

    public function leave(Node\Expr\New_ $node, Scope $scope)
    {
        if (! ($node->class instanceof Node\Name)) {
            return null;
        }

        $scope->setType(
            $node,
            new ObjectType($node->class->toString()),
        );
    }
}
