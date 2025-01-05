<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Contracts\AstLocator as AstLocatorContract;
use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node;

class BypassAstLocator implements AstLocatorContract
{
    public function __construct(
        private Node $node,
    ) {}

    public function getSource(Symbol $symbol): ?Node
    {
        return $this->node;
    }
}
