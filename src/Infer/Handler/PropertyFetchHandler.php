<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class PropertyFetchHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr\PropertyFetch;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        // Only string property names support.
        if (! $name = ($node->name->name ?? null)) {
            return;
        }

        $type = $scope->getType($node->var);

        $scope->setType($node, $type->getPropertyFetchType($name));
    }
}
