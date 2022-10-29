<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use Throwable;

class ThrowHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Throw_;
    }

    public function leave(Node\Stmt\Throw_ $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        if (! $scope->getType($node->expr)->isInstanceOf(Throwable::class)) {
            return;
        }

        $fnType = $scope->function();

        $fnType->exceptions = [
            ...$fnType->exceptions,
            $scope->getType($node->expr),
        ];
    }
}
