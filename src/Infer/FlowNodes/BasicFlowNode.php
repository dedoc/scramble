<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use PhpParser\Node\Stmt;

class BasicFlowNode extends AbstractFlowNode
{
    public function __construct(
        public readonly Stmt $statement,
        array $antecedents,
    )
    {
        parent::__construct($antecedents);
    }
}
