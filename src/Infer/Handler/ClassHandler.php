<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class ClassHandler implements CreatesScope
{
    public function createScope(Scope $scope, Node $node): Scope
    {
        return $scope->createChildScope(clone $scope->context);
    }

    public function shouldHandle($node): bool
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node\Stmt\Class_ $node, Scope $scope): void
    {
        $parentDefinition = $node->extends
            ? ($scope->index->getClass($node->extends->toString())?->getData() ?: null)
            : null;

        $scope->context->setClassDefinition($classDefinition = new ClassDefinition(
            name: $node->namespacedName ? $node->namespacedName->toString() : 'anonymous@class',
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: $parentDefinition?->properties ?: [],
            methods: $parentDefinition?->methods ?: [],
            parentFqn: $node->extends ? $node->extends->toString() : null,
        ));

        if ($scope->index instanceof Index) { // @todo no need in this handler at all
            $scope->index->registerClassDefinition($classDefinition);
        }
    }
}
