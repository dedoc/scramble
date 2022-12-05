<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class NewHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr\New_;
    }

    public function leave(Node\Expr\New_ $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        if (! ($node->class instanceof Node\Name)) {
            return;
        }

        $scope->setType(
            $node,
            new ObjectType($node->class->toString()),
        );
    }
}
