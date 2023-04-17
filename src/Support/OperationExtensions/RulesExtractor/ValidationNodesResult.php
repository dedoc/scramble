<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

class ValidationNodesResult
{
    public $node;

    public function __construct($node)
    {
        $this->node = $node;
    }
}
