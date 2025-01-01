<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use PhpParser\Node\Expr;

class TerminateFlowNode extends AbstractFlowNode
{
    const KIND_RETURN = 0;

    public function __construct(
        public readonly ?Expr $expression,
        public readonly int $kind,
        array $antecedents,
    )
    {
        parent::__construct($antecedents);
    }
}
