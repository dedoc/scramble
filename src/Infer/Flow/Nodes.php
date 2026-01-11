<?php

namespace Dedoc\Scramble\Infer\Flow;

use PhpParser\Node\Expr;

class Nodes
{
    public StartNode $start;

    public function __construct()
    {
        $this->start = new StartNode;
    }

    public function getExprNode(Expr $node): Node {}

    public function push(Node $node): self
    {
        $headNode = $this->getHeadNode();

        $node->parentNodes[] = $headNode;
        $headNode->childNodes[] = $node;

        return $this;
    }

    public function getHeadNode(): Node
    {
        $headNode = $tempHeadNode = $this->start;

        while ($tempHeadNode !== null) {
            $tempHeadNode = $headNode->childNodes[count($headNode->childNodes) - 1] ?? null;

            if ($tempHeadNode !== null) {
                $headNode = $tempHeadNode;
            }
        }

        return $headNode;
    }
}
