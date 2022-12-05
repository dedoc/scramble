<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use Throwable;

class ThrowHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Stmt\Throw_;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        if (! $scope->isInFunction()) {
            return;
        }

        if (! $scope->getType($node->expr)->isInstanceOf(Throwable::class)) {
            return;
        }

        $fnType = $scope->function();

        $fnType->exceptions = [
            ...$fnType->exceptions,
            $scope->getType($node->expr),
        ];
    }
}
