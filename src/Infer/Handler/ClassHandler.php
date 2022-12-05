<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Contracts\LeaveTrait;
use Dedoc\Scramble\Infer\Contracts\ScopeCreator;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class ClassHandler implements ScopeCreator, HandlerInterface
{
    use LeaveTrait;

    public function createScope(Scope $scope, Node $node): Scope
    {
        return $scope->createChildScope(clone $scope->context);
    }

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

        $scope->context->setClass(
            $classType = new ObjectType($node->name ? $scope->resolveName($node->name->toString()) : 'anonymous@class'),
        );

        $scope->setType($node, $classType);
    }
}
