<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\BinaryOpType;
use Dedoc\Scramble\Support\Type\CoalesceType;
use PhpParser\Node;
use PhpParser\Node\Expr;

class AssignOpHandler
{
    private const OPERATORS = [
        Expr\AssignOp\Plus::class => '+',
        Expr\AssignOp\Minus::class => '-',
        Expr\AssignOp\Mul::class => '*',
        Expr\AssignOp\Div::class => '/',
        Expr\AssignOp\Mod::class => '%',
        Expr\AssignOp\Pow::class => '**',
        Expr\AssignOp\Concat::class => '.',
    ];

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Expr\AssignOp\Coalesce || isset(self::OPERATORS[$node::class]);
    }

    public function leave(Expr\AssignOp $node, Scope $scope): void
    {
        $previousType = $scope->getType($node->var);
        $operandType = $scope->getType($node->expr);

        $type = $node instanceof Expr\AssignOp\Coalesce
            ? new CoalesceType($previousType, $operandType)
            : new BinaryOpType(
                $previousType,
                $operandType,
                self::OPERATORS[$node::class],
                foldsNumericLiterals: false,
            );

        if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $scope->addVariableType($node->getStartLine(), $node->var->name, $type);
        }

        $scope->setType($node, $type);
    }
}
