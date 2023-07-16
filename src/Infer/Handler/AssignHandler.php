<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use PhpParser\Node;

class AssignHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\Assign;
    }

    public function leave(Node\Expr\Assign $node, Scope $scope)
    {
        if ($node->var instanceof Node\Expr\Variable) {
            $this->handleVarAssignment($node, $node->var, $scope);
        }

        if ($node->var instanceof Node\Expr\PropertyFetch) {
            $this->handlePropertyAssignment($node, $node->var, $scope);
        }
    }

    private function handleVarAssignment(Node\Expr\Assign $node, Node\Expr\Variable $var, Scope $scope)
    {
        $scope->addVariableType(
            $node->getAttribute('startLine'),
            (string) $var->name,
            $type = $scope->getType($node->expr),
        );

        $scope->setType($node, $type);
    }

    private function handlePropertyAssignment(Node\Expr\Assign $node, Node\Expr\PropertyFetch $propertyFetchNode, Scope $scope)
    {
        if (
            ! $scope->isInClass()
            || ! $propertyFetchNode->var instanceof Node\Expr\Variable
            || $propertyFetchNode->var->name !== 'this'
            || ! $propertyFetchNode->name instanceof Node\Identifier
        ) {
            return;
        }

        if ($scope->functionDefinition()->type->name === '__construct') {
            return;
        }

        if (! isset($scope->classDefinition()->properties[$propertyFetchNode->name->toString()]->type)) {
            return;
        }

        if (! ($templateType = $scope->classDefinition()->properties[$propertyFetchNode->name->toString()]->type) instanceof TemplateType) {
            return;
        }

        $scope->functionDefinition()->sideEffects[] = new SelfTemplateDefinition(
            $templateType->name,
            $scope->getType($node->expr),
        );
    }
}
