<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\Dependency\ClassDependency;
use Dedoc\Scramble\Support\Type\Reference\Dependency\MethodDependency;
use Dedoc\Scramble\Support\Type\Reference\Dependency\PropertyDependency;
use Dedoc\Scramble\Support\Type\TypeWalker;
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
        $parentDefinition = $node->extends
            ? $scope->index->getClassDefinition($node->extends->toString())
            : null;

        $scope->context->setClassDefinition($classDefinition = new ClassDefinition(
            name: $node->namespacedName ? $node->namespacedName->toString() : 'anonymous@class',
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: $parentDefinition?->properties ?: [],
            methods: $parentDefinition?->methods ?: [],
            parentFqn: $node->extends ? $node->extends->toString() : null,
        ));

        $scope->index->registerClassDefinition($classDefinition);
    }

    public function leave(Node\Stmt\Class_ $node, Scope $scope)
    {
        $classDefinition = $scope->classDefinition();

        // Resolving all self reference returns from methods
        foreach ($classDefinition->methods as $name => $methodDefinition) {
            $references = (new TypeWalker)->find(
                $returnType = $methodDefinition->type->getReturnType(),
                fn ($t) => $t instanceof AbstractReferenceType,
            );

            if (! $references) {
                continue;
            }

            $dependencies = array_merge(...array_map(fn ($r) => $r->dependencies(), $references));

            $hasSelfReferences = collect($dependencies)->some(function ($d) use ($classDefinition) {
                return ($d instanceof PropertyDependency || $d instanceof MethodDependency || $d instanceof ClassDependency)
                    && $d->class === $classDefinition->name;
            });

            $returnType = $hasSelfReferences
                ? (new ReferenceTypeResolver($scope->index))->resolve($scope, $returnType)
                : $returnType;

            $methodDefinition->type->setReturnType($returnType);
        }
    }
}
