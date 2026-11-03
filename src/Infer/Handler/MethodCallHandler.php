<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Reference\MethodCall;
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

    public function enter(Expr\MethodCall $node, Scope $scope): void
    {
        if ($node->var instanceof Expr\MethodCall) {
            $node->var->setAttribute('scramble:isMethodCallReceiver', true);
        }
    }

    public function leave(Expr\MethodCall $node, Scope $scope): void
    {
        if ($node->getAttribute('scramble:isMethodCallReceiver', false)) {
            return;
        }

        if (! $varName = $this->getPotentiallyMutatingVarName($node)) {
            return;
        }

        $calls = [];
        $callNode = $node;

        do {
            $type = $scope->getType($callNode);

            if (! $type instanceof MethodCallReferenceType) {
                return;
            }

            $calls[] = new MethodCall($type->methodName, $type->arguments);
            $callNode = $callNode->var;
        } while ($callNode instanceof Expr\MethodCall);

        $scope->addVariableType(
            $node->getAttribute('endLine', $node->getAttribute('startLine')) + 1,
            $varName,
            new PotentialMethodMutatingCallType(
                $scope->getType($callNode),
                array_reverse($calls),
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
