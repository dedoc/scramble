<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node\Expr;

class ConditionNode extends AbstractNode
{
    public function __construct(
        public ?Expr $value, // May be null when `return;`
        array $parentNodes = [],
        array $childNodes = [],
    ) {
        parent::__construct($parentNodes, $childNodes);
    }
}
