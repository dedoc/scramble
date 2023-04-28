<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class ClassHandler implements CreatesScope
{
    public function createScope(Scope $scope, Node $node): Scope
    {
        return $scope->createChildScope(clone $scope->context);
    }

    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node\Stmt\Class_ $node, Scope $scope)
    {
        $scope->context->setClassDefinition($classDefinition = new ClassDefinition(
            name: $node->name ? $scope->resolveName($node->name->toString()) : 'anonymous@class',
            templateTypes: [],
            properties: [],
            methods: [],
            parentFqn: $node->extends ? $scope->resolveName($node->extends->toString()) : null,
        ));

        $scope->index->registerClassDefinition($classDefinition);

        // REMOVE EVERYTHING ELSE BELLOW?
//        $scope->context->setClass(
//            $classType = new ObjectType($node->name ? $scope->resolveName($node->name->toString()) : 'anonymous@class'),
//        );
//
//        $scope->index->registerClassType($scope->resolveName($node->name->toString()), $classType);
//
//        $scope->setType($node, $classType);
    }
}
