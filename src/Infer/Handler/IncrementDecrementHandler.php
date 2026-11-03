<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\BinaryOpType;
use Dedoc\Scramble\Support\Type\IntegerType;
use PhpParser\Node;
use PhpParser\Node\Expr;

class IncrementDecrementHandler
{
    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Expr\PreInc
            || $node instanceof Expr\PostInc
            || $node instanceof Expr\PreDec
            || $node instanceof Expr\PostDec;
    }

    public function leave(Expr\PreInc|Expr\PostInc|Expr\PreDec|Expr\PostDec $node, Scope $scope): void
    {
        $previousType = $scope->getType($node->var);
        $type = new BinaryOpType(
            $previousType,
            new IntegerType, // Widen numeric literals while retaining arithmetic inference rules.
            $node instanceof Expr\PreInc || $node instanceof Expr\PostInc ? '+' : '-',
        );

        if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $scope->addVariableType($node->getStartLine(), $node->var->name, $type);
        }

        $scope->setType(
            $node,
            $node instanceof Expr\PostInc || $node instanceof Expr\PostDec ? $previousType : $type,
        );
    }
}
