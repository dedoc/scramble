<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
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
}
