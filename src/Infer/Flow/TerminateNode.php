<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node\Expr;

class TerminateNode extends AbstractNode
{
    public function __construct(
        public TerminationKind $kind,
        public ?Expr $value, // May be null when `return;`
    ) {}
}
