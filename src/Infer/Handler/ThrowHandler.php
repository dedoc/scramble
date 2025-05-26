<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
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

        /** @var ObjectType $exceptionType */

        $fnDefinition = $scope->functionDefinition();

        $fnDefinition->type->exceptions = [
            ...$fnDefinition->type->exceptions,
            $exceptionType,
        ];
    }
}
