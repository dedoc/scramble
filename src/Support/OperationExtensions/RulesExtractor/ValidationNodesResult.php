<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer\Scope\Scope;

class ValidationNodesResult
{
    public $node;

    public Scope $scope;

    public function __construct($node, Scope $scope)
    {
        $this->node = $node;
        $this->scope = $scope;
    }
}
