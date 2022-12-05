<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use PhpParser\Node;

class ArrayItemHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr\ArrayItem;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        $scope->setType(
            $node,
            new ArrayItemType_(
                $node->key->value ?? null,
                $scope->getType($node->value),
                $isOptional = false,
            )
        );
    }
}
