<?php

namespace Dedoc\Scramble\Infer\Flow;

class StatementNode extends AbstractNode
{
    public function __construct(public \PhpParser\Node $parserNode) {}
}
