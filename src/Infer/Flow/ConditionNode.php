<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node\Stmt\If_;

class ConditionNode extends AbstractNode
{
    public function __construct(public If_ $parserNode)
    {
    }
}
