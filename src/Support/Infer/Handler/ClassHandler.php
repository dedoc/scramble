<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class ClassHandler implements CreatesScope
{
    public function createScope(Scope $scope): Scope
    {
        return $scope->createChildScope(clone $scope->context);
    }

    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node\Stmt\Class_ $node, Scope $scope)
    {
        $scope->context->setClass(
            new ObjectType($node->name ? $scope->resolveName($node->name->toString()) : 'anonymous@class'),
        );
    }

    public function leave(Node\Stmt\Class_ $node, Scope $scope)
    {
        $scope->setType($node, $scope->context->class);
    }
}
