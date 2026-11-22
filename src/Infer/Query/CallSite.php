<?php

namespace Dedoc\Scramble\Infer\Query;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class CallSite
{
    public function __construct(
        private readonly CallLike $node,
        private readonly Scope $scope,
    ) {}

    public function node(): CallLike
    {
        return $this->node;
    }

    public function scope(): Scope
    {
        return $this->scope;
    }

    public function phpDoc(): ?PhpDocNode
    {
        $phpDoc = $this->commentNode()->getAttribute('parsedPhpDoc', $this->node->getAttribute('parsedPhpDoc'));

        return $phpDoc instanceof PhpDocNode ? $phpDoc : null;
    }

    public function commentNode(): Node
    {
        $node = $this->node->getAttribute('scramble:commentNode');

        return $node instanceof Node ? $node : $this->node;
    }
}
