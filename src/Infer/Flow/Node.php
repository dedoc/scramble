<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node as PhpParserNode;

interface Node
{
    public function toDotId(Nodes $nodes): string;

    public function toDot(Nodes $nodes): string;

    /** @phpstan-assert-if-true PhpParserNode\Stmt\Expression $this->getParserNode() */
    public function definesVariable(string $varName): bool;

    public function getParserNode(): ?PhpParserNode;
}
