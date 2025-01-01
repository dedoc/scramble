<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\Variable;

class AssignmentFlowNode extends AbstractFlowNode
{
    const KIND_ASSIGN = 0;

    const KIND_ASSIGN_COMPOUND = 1;

    public function __construct(
        public readonly Assign|AssignOp $expression,
        public readonly int $kind,
        array $antecedents,
    )
    {
        parent::__construct($antecedents);
    }

    public function assigningTo(Expr $expression): bool
    {
        if (
            $expression instanceof Variable
            && $this->expression->var instanceof Variable
            && $expression->name === $this->expression->var->name
        ) {
            return true;
        }

        return false; // todo: array dim fetch, property fetch
    }
}
