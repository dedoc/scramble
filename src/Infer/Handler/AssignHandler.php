<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class AssignHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Expr\Assign;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        if (! $node->var instanceof Node\Expr\Variable) {
            return;
        }

        $scope->addVariableType(
            $node->getAttribute('startLine'),
            (string) $node->var->name,
            $type = $scope->getType($node->expr),
        );

        $scope->setType($node, $type);
    }
}
