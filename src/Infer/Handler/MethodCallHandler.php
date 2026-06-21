<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PotentialMethodMutatingCallType;
use PhpParser\Node;
use PhpParser\Node\Expr;

class MethodCallHandler
{
    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Expr\MethodCall;
    }

    public function leave(Expr\MethodCall $node, Scope $scope): void
    {
        if (! $node->var instanceof Expr\Variable || ! is_string($node->var->name)) {
            return;
        }

        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        $type = $scope->getType($node);

        if (! $type instanceof MethodCallReferenceType) {
            return;
        }

        $scope->addVariableType(
            $node->getAttribute('endLine', $node->getAttribute('startLine')) + 1,
            $node->var->name,
            new PotentialMethodMutatingCallType(
                $type->callee,
                $type->methodName,
                $type->arguments,
            ),
        );
    }
}
