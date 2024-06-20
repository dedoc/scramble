<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use Throwable;

class ThrowHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Throw_;
    }

    public function leave(Node\Expr\Throw_ $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        $exceptionType = $scope->getType($node->expr);

        if (! $exceptionType->isInstanceOf(Throwable::class)) {
            return;
        }

        $fnDefinition = $scope->functionDefinition();

        $fnDefinition->type->exceptions = [
            ...$fnDefinition->type->exceptions,
            $exceptionType,
        ];
    }
}
