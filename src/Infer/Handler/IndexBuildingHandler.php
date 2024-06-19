<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class IndexBuildingHandler
{
    public function __construct(
        private array $indexBuilders,
    ) {}

    public function shouldHandle($node)
    {
        return true;
    }

    public function leave(Node $node, Scope $scope)
    {
        foreach ($this->indexBuilders as $indexBuilder) {
            $indexBuilder->afterAnalyzedNode($scope, $node);
        }
    }
}
