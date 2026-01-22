<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node as PhpParserNode;

class StatementNode extends AbstractNode
{
    public function __construct(public PhpParserNode $parserNode) {}

    public function getParserNode(): PhpParserNode
    {
        return $this->parserNode;
    }
}
