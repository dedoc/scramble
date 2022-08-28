<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class ClassHandler implements CreatesScope
{
    public function createScope(Scope $scope): Scope
    {
        return new Scope(
            clone $scope->context,
            $scope->namesResolver,
            $scope
        );
    }

    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node\Stmt\Class_ $node)
    {
        $node->getAttribute('scope')->context->setClass(
            new ObjectType($node->name ? $node->getAttribute('scope')->resolveName($node->name->toString()) : 'anonymous@class'),
        );
    }
}
