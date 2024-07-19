<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

interface IndexBuilder
{
    public function afterAnalyzedNode(Scope $scope, Node $node): void;
}
