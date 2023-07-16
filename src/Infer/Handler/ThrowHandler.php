<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
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

        // Temporary solution for reference type. Not sure what is the solution here.
        // Maybe it should add exceptions to the function exceptions list and then resolve
        // them all.
        $exceptionType = $scope->getType($node->expr);
        $exceptionType = $exceptionType instanceof NewCallReferenceType
            ? new ObjectType($exceptionType->name)
            : $exceptionType;

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
