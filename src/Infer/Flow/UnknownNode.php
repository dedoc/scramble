<?php

namespace Dedoc\Scramble\Infer\Flow;

class UnknownNode extends AbstractNode
{
    public function __construct(public \PhpParser\Node $parserNode)
    {
    }
}
