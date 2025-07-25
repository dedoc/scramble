<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

/**
 * @implements IndexBuilder<array<string, mixed>>
 */
class ScopeCollector implements IndexBuilder
{
    public function __construct(
        public ?Scope $scope = null,
    ) {}

    public function afterAnalyzedNode(Scope $scope, Node $node): void
    {
        $this->scope = $scope;
    }
}
