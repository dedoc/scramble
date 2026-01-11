<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node\Expr;

class AssignNode extends AbstractNode
{
    public function __construct(
        public Expr $var,
        public Expr $value,
        array $predecessors = [],
        array $successors = [],
    )
    {
        parent::__construct($predecessors, $successors);
    }
}
