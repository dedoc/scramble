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
        if (! $varName = $this->getPotentiallyMutatingVarName($node)) {
            return;
        }

        $type = $scope->getType($node);

        if (! $type instanceof MethodCallReferenceType) {
            return;
        }

        $scope->addVariableType(
            $node->getAttribute('endLine', $node->getAttribute('startLine')) + 1,
            $varName,
            new PotentialMethodMutatingCallType(
                $type->callee,
                $type->methodName,
                $type->arguments,
            ),
        );
    }

    private function getPotentiallyMutatingVarName(Expr\MethodCall $node): ?string
    {
        if ($node->var instanceof Expr\MethodCall) {
            return $this->getPotentiallyMutatingVarName($node->var);
        }

        if (! $node->var instanceof Expr\Variable || ! is_string($node->var->name)) {
            return null;
        }

        if (! $node->name instanceof Node\Identifier) {
            return null;
        }

        return $node->var->name;
    }
}
