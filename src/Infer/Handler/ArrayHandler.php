<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use PhpParser\Node;

class ArrayHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr\Array_;
    }

    /**
     * @param Node $node
     * @param Scope $scope
     * @return void
     */
    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        $arrayItems = collect($node->items)
            ->filter()
            ->map(fn (Node\Expr\ArrayItem $arrayItem) => $scope->getType($arrayItem))
            ->all();

        $scope->setType($node, new ArrayType($arrayItems));
    }
}
