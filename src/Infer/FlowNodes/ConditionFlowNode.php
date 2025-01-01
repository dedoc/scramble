<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use PhpParser\Node\Stmt;

class ConditionFlowNode extends AbstractFlowNode
{
    public function __construct(
        public readonly array $truthyConditions,
        public readonly array $falsyConditions,
        public readonly Stmt $statement,
        public readonly bool $isTrue,
        array $antecedents,
    )
    {
        parent::__construct($antecedents);
    }
}
